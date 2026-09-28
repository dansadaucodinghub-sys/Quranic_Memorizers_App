const DATABASE_NAME = 'qmdb-offline-venue-v1';
const DATABASE_VERSION = 1;
const MAX_PENDING_CHANGES = 100;
const MAX_CHANGE_BYTES = 32768;
const ALLOWED_OPERATIONS = new Set([
    'PARTICIPANT_CHECK_IN', 'PARTICIPANT_ABSENT', 'PARTICIPANT_CALLED', 'PARTICIPANT_READY',
    'PERFORMANCE_STARTED', 'PERFORMANCE_INTERRUPTED', 'PERFORMANCE_RESUMED', 'PERFORMANCE_COMPLETED',
    'SCORE_DRAFT_SAVED', 'SCORE_SHEET_SUBMITTED', 'VENUE_INCIDENT_RECORDED',
    'OPERATIONAL_NOTE_RECORDED', 'JUDGE_ACKNOWLEDGEMENT_RECORDED',
]);

export class OfflineVenueStore {
    constructor(indexedDb = globalThis.indexedDB) {
        this.indexedDb = indexedDb;
    }

    async open() {
        if (!this.indexedDb) throw new Error('OFFLINE_STORAGE_UNAVAILABLE');
        return new Promise((resolve, reject) => {
            const request = this.indexedDb.open(DATABASE_NAME, DATABASE_VERSION);
            request.onupgradeneeded = () => {
                const database = request.result;
                database.createObjectStore('authority', { keyPath: 'key' });
                database.createObjectStore('entities', { keyPath: ['type', 'id'] });
                database.createObjectStore('pending', { keyPath: 'changeId' }).createIndex('sequence', 'sequence', { unique: true });
                database.createObjectStore('history', { keyPath: 'changeId' });
                database.createObjectStore('keys', { keyPath: 'key' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error ?? new Error('OFFLINE_STORAGE_OPEN_FAILED'));
        });
    }

    async countPending() {
        const database = await this.open();
        return this.request(database.transaction('pending', 'readonly').objectStore('pending').count());
    }

    async enqueue(operation, entityType, entityId, expectedVersion, payload) {
        if (!ALLOWED_OPERATIONS.has(operation)) throw new Error('INVALID_OPERATION');
        const encoded = new TextEncoder().encode(JSON.stringify(payload));
        if (encoded.byteLength > MAX_CHANGE_BYTES) throw new Error('CHANGE_TOO_LARGE');
        const database = await this.open();
        const count = await this.request(database.transaction('pending', 'readonly').objectStore('pending').count());
        if (count >= MAX_PENDING_CHANGES) throw new Error('PENDING_QUEUE_FULL');
        const authority = await this.authority(database);
        if (!authority || Date.parse(authority.expiresAt) <= Date.now() || authority.revoked === true) throw new Error('OFFLINE_AUTHORITY_EXPIRED');
        const sequence = Number(authority.lastSequence ?? 0) + 1;
        const changeId = crypto.randomUUID();
        const payloadHash = await this.sha256(encoded);
        const change = { changeId, sequence, operation, entityType, entityId, expectedVersion, payload, payloadHash, occurredAt: new Date().toISOString(), state: 'PENDING' };
        const transaction = database.transaction(['pending', 'authority'], 'readwrite');
        transaction.objectStore('pending').add(change);
        transaction.objectStore('authority').put({ ...authority, key: 'active', lastSequence: sequence });
        await this.complete(transaction);
        return change;
    }

    async importVerifiedPackage(envelope, verifySignature) {
        if (!envelope || typeof envelope !== 'object' || !Array.isArray(envelope.entities)) throw new Error('INVALID_PACKAGE');
        if (!envelope.manifest || typeof envelope.manifest !== 'object') throw new Error('INVALID_PACKAGE');
        if (!Array.isArray(envelope.manifest.allowed_operations) || envelope.manifest.allowed_operations.some(operation => !ALLOWED_OPERATIONS.has(operation))) throw new Error('PACKAGE_SCOPE_INVALID');
        if (typeof envelope.manifest.package_id !== 'string' || typeof envelope.manifest.device_id !== 'string') throw new Error('INVALID_PACKAGE');
        const canonicalManifest = canonicalJson(envelope.manifest);
        if (!(await verifySignature(canonicalManifest, envelope.signature))) throw new Error('PACKAGE_SIGNATURE_INVALID');
        if (Date.parse(envelope.manifest.expires_at) <= Date.now()) throw new Error('PACKAGE_EXPIRED');
        const database = await this.open();
        const transaction = database.transaction(['authority', 'entities'], 'readwrite');
        const entities = transaction.objectStore('entities');
        entities.clear();
        for (const entity of envelope.entities) entities.put(entity);
        transaction.objectStore('authority').put({ key: 'active', packageId: envelope.manifest.package_id, deviceId: envelope.manifest.device_id, expiresAt: envelope.manifest.expires_at, allowedOperations: envelope.manifest.allowed_operations, lastSequence: 0, revoked: false });
        await this.complete(transaction);
    }

    async recordSyncResult(receipts, conflicts = []) {
        if (!Array.isArray(receipts) || !Array.isArray(conflicts)) throw new Error('SYNC_RESULT_INVALID');
        const database = await this.open();
        const transaction = database.transaction(['pending', 'history'], 'readwrite');
        const pending = transaction.objectStore('pending');
        const history = transaction.objectStore('history');
        for (const receipt of receipts) {
            if (!receipt || typeof receipt.change_id !== 'string' || !['ACCEPTED', 'REJECTED', 'CONFLICT', 'DUPLICATE'].includes(receipt.status)) throw new Error('SYNC_RECEIPT_INVALID');
            pending.delete(receipt.change_id);
            history.put({ changeId: receipt.change_id, status: receipt.status, reason: receipt.reason ?? null, receivedAt: new Date().toISOString() });
        }
        for (const conflict of conflicts) {
            if (!conflict || typeof conflict.change_id !== 'string') throw new Error('SYNC_CONFLICT_INVALID');
            history.put({ changeId: conflict.change_id, status: 'CONFLICT', reason: conflict.reason ?? 'SOURCE_CHANGED', receivedAt: new Date().toISOString() });
        }
        await this.complete(transaction);
    }

    async revokeAuthority() {
        const database = await this.open();
        const authority = await this.authority(database);
        if (!authority) return;
        const transaction = database.transaction('authority', 'readwrite');
        transaction.objectStore('authority').put({ ...authority, key: 'active', revoked: true });
        await this.complete(transaction);
    }

    async clear() {
        const database = await this.open();
        const transaction = database.transaction(['authority', 'entities', 'pending', 'history', 'keys'], 'readwrite');
        for (const store of ['authority', 'entities', 'pending', 'history', 'keys']) transaction.objectStore(store).clear();
        await this.complete(transaction);
    }

    async authority(database = null) {
        const connection = database ?? await this.open();
        return this.request(connection.transaction('authority', 'readonly').objectStore('authority').get('active'));
    }

    async sha256(bytes) {
        return [...new Uint8Array(await crypto.subtle.digest('SHA-256', bytes))].map(value => value.toString(16).padStart(2, '0')).join('');
    }

    request(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error ?? new Error('OFFLINE_STORAGE_REQUEST_FAILED'));
        });
    }

    complete(transaction) {
        return new Promise((resolve, reject) => {
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error ?? new Error('OFFLINE_STORAGE_TRANSACTION_FAILED'));
            transaction.onabort = () => reject(transaction.error ?? new Error('OFFLINE_STORAGE_TRANSACTION_ABORTED'));
        });
    }
}

export function canonicalJson(value) {
    if (Array.isArray(value)) return `[${value.map(canonicalJson).join(',')}]`;
    if (value && typeof value === 'object') return `{${Object.keys(value).sort().map(key => `${JSON.stringify(key)}:${canonicalJson(value[key])}`).join(',')}}`;
    return JSON.stringify(value);
}

export class OfflineVenueController {
    constructor(root, store = new OfflineVenueStore()) {
        this.root = root;
        this.store = store;
        this.disposed = false;
    }

    async start() {
        addEventListener('online', this.renderStatus);
        addEventListener('offline', this.renderStatus);
        this.root.querySelector('[data-offline-clear]')?.addEventListener('click', this.clear);
        this.root.querySelector('[data-offline-sync]')?.addEventListener('click', this.sync);
        this.root.addEventListener('qmdb:offline-sync-result', this.syncResult);
        this.root.addEventListener('qmdb:offline-authority-revoked', this.authorityRevoked);
        if ('serviceWorker' in navigator) await navigator.serviceWorker.register('/offline-service-worker.js', { scope: '/' });
        await this.renderStatus();
    }

    renderStatus = async () => {
        if (this.disposed) return;
        const status = this.root.querySelector('[data-network-status]');
        if (status) status.textContent = navigator.onLine ? 'Online' : 'Offline';
        const count = this.root.querySelector('[data-pending-count]');
        try { if (count) count.textContent = String(await this.store.countPending()); } catch { if (count) count.textContent = 'unavailable'; }
        const announcement = this.root.querySelector('[data-offline-announcement]');
        if (announcement) announcement.textContent = navigator.onLine ? 'Network connection available.' : 'Offline mode active. Changes remain queued on this device.';
    };

    clear = async () => {
        if (!confirm('Clear this device’s offline package, keys, and queued changes?')) return;
        await this.store.clear();
        await this.renderStatus();
        this.root.querySelector('[data-offline-clear]')?.focus();
    };

    sync = () => {
        const status = this.root.querySelector('[data-offline-status]');
        status?.setAttribute('aria-busy', 'true');
        this.root.dispatchEvent(new CustomEvent('qmdb:offline-sync-requested', { bubbles: true }));
    };

    syncResult = async event => {
        const detail = event.detail ?? {};
        await this.store.recordSyncResult(detail.receipts ?? [], detail.conflicts ?? []);
        const totals = { ACCEPTED: 0, REJECTED: 0, CONFLICT: 0, DUPLICATE: 0 };
        for (const receipt of detail.receipts ?? []) if (receipt.status in totals) totals[receipt.status] += 1;
        totals.CONFLICT += (detail.conflicts ?? []).length;
        for (const [status, count] of Object.entries(totals)) {
            const target = this.root.querySelector(`[data-receipt-${status.toLowerCase()}]`);
            if (target) target.textContent = String(count);
        }
        this.root.querySelector('[data-offline-status]')?.setAttribute('aria-busy', 'false');
        await this.renderStatus();
        const announcement = this.root.querySelector('[data-offline-announcement]');
        if (announcement) announcement.textContent = `Sync completed. ${totals.ACCEPTED} accepted, ${totals.REJECTED} rejected, ${totals.CONFLICT} conflicts, ${totals.DUPLICATE} duplicates.`;
        this.root.querySelector('[data-offline-sync]')?.focus();
    };

    authorityRevoked = async () => {
        await this.store.revokeAuthority();
        const announcement = this.root.querySelector('[data-offline-announcement]');
        if (announcement) announcement.textContent = 'Offline package authority was revoked. Synchronize before continuing.';
    };

    dispose() {
        this.disposed = true;
        removeEventListener('online', this.renderStatus);
        removeEventListener('offline', this.renderStatus);
        this.root.removeEventListener('qmdb:offline-sync-result', this.syncResult);
        this.root.removeEventListener('qmdb:offline-authority-revoked', this.authorityRevoked);
    }
}

for (const root of document.querySelectorAll('[data-offline-venue-root]')) new OfflineVenueController(root).start().catch(() => {});
