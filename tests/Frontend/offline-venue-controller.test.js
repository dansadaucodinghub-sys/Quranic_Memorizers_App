import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://qmdb.test/workspace/offline-devices' });
globalThis.document = dom.window.document;
globalThis.CustomEvent = dom.window.CustomEvent;
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
globalThis.addEventListener = dom.window.addEventListener.bind(dom.window);
globalThis.removeEventListener = dom.window.removeEventListener.bind(dom.window);

const module = await import('../../public/assets/js/offline-venue-controller.js');

test('canonical package JSON is deterministic at every object level', () => {
    assert.equal(module.canonicalJson({ z: 1, a: { d: 4, c: 3 } }), '{"a":{"c":3,"d":4},"z":1}');
});

test('offline client has bounded queues and never uses web storage for authority', async () => {
    const source = await readFile(new URL('../../public/assets/js/offline-venue-controller.js', import.meta.url), 'utf8');
    assert.match(source, /MAX_PENDING_CHANGES = 100/);
    assert.match(source, /MAX_CHANGE_BYTES = 32768/);
    assert.doesNotMatch(source, /localStorage|sessionStorage/);
    assert.match(source, /OFFLINE_AUTHORITY_EXPIRED/);
    assert.match(source, /queued changes\?/);
});

test('service worker caches only the public offline controller asset', async () => {
    const source = await readFile(new URL('../../public/offline-service-worker.js', import.meta.url), 'utf8');
    assert.match(source, /PUBLIC_SHELL = \['\/assets\/js\/offline-venue-controller\.js'\]/);
    assert.doesNotMatch(source, /workspace\/offline|offline\/v1|Authorization|Cookie/);
    assert.match(source, /request\.method !== 'GET'/);
    assert.match(source, /response\.type === 'basic'/);
    assert.match(source, /keys\.filter\(key => key !== CACHE_NAME\)/);
});

test('offline package import is fail closed and the exact operation allowlist is enforced', async () => {
    const source = await readFile(new URL('../../public/assets/js/offline-venue-controller.js', import.meta.url), 'utf8');
    for (const operation of ['PARTICIPANT_CHECK_IN', 'PERFORMANCE_COMPLETED', 'SCORE_DRAFT_SAVED', 'SCORE_SHEET_SUBMITTED', 'VENUE_INCIDENT_RECORDED', 'OPERATIONAL_NOTE_RECORDED', 'JUDGE_ACKNOWLEDGEMENT_RECORDED']) {
        assert.match(source, new RegExp(`'${operation}'`));
    }
    assert.match(source, /PACKAGE_SIGNATURE_INVALID/);
    assert.match(source, /PACKAGE_EXPIRED/);
    assert.match(source, /PACKAGE_SCOPE_INVALID/);
    assert.match(source, /crypto\.randomUUID\(\)/);
    assert.match(source, /lastSequence: sequence/);
});

test('controller announces network and synchronization state and restores focus', async () => {
    document.body.innerHTML = `<section data-offline-venue-root><div data-offline-status aria-busy="false"><span data-network-status></span><span data-pending-count></span><span data-offline-announcement></span><span data-receipt-accepted></span><span data-receipt-rejected></span><span data-receipt-conflict></span><span data-receipt-duplicate></span></div><button data-offline-sync>Sync</button><button data-offline-clear>Clear</button></section>`;
    const root = document.querySelector('[data-offline-venue-root]');
    const calls = [];
    const store = {
        countPending: async () => 2,
        recordSyncResult: async (receipts, conflicts) => calls.push({ receipts, conflicts }),
        revokeAuthority: async () => calls.push('revoked'),
        clear: async () => calls.push('cleared'),
    };
    const controller = new module.OfflineVenueController(root, store);
    await controller.start();
    assert.equal(root.querySelector('[data-pending-count]').textContent, '2');
    controller.sync();
    assert.equal(root.querySelector('[data-offline-status]').getAttribute('aria-busy'), 'true');
    await controller.syncResult({ detail: { receipts: [{ change_id: 'a', status: 'ACCEPTED' }, { change_id: 'b', status: 'DUPLICATE' }], conflicts: [{ change_id: 'c' }] } });
    assert.equal(root.querySelector('[data-receipt-accepted]').textContent, '1');
    assert.equal(root.querySelector('[data-receipt-conflict]').textContent, '1');
    assert.equal(root.querySelector('[data-receipt-duplicate]').textContent, '1');
    assert.match(root.querySelector('[data-offline-announcement]').textContent, /Sync completed/);
    assert.equal(document.activeElement, root.querySelector('[data-offline-sync]'));
    await controller.authorityRevoked();
    assert.ok(calls.includes('revoked'));
    controller.dispose();
});

test('P13 fragment provides status semantics, counts, no-script fallback, and no positive tabindex', async () => {
    const source = await readFile(new URL('../../resources/views/fragments/p13-portal.php', import.meta.url), 'utf8');
    assert.match(source, /role="status"/);
    assert.match(source, /aria-live="polite"/);
    assert.match(source, /aria-busy="false"/);
    assert.match(source, /data-receipt-accepted/);
    assert.match(source, /data-receipt-rejected/);
    assert.match(source, /data-receipt-conflict/);
    assert.match(source, /<noscript>/);
    assert.doesNotMatch(source, /tabindex="[1-9]/);
    assert.match(source, /dir="ltr"/);
});
