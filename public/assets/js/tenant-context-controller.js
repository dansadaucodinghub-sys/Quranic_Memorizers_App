const META_NAME = 'qmdb-tenant-context-version';
const MESSAGE_TYPE = 'tenant-context-version';

function validVersion(value) {
    if (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value)) return 0;
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : 0;
}

export function tenantContextVersion(documentRoot = document) {
    return validVersion(documentRoot.querySelector(`meta[name="${META_NAME}"]`)?.content ?? '');
}

export function adoptTenantContextResponse(response, documentRoot = document) {
    const version = validVersion(response?.headers?.get('X-QMDB-Tenant-Context-Version') ?? '');
    if (!version) return 0;
    const meta = documentRoot.querySelector(`meta[name="${META_NAME}"]`);
    if (meta) meta.content = String(version);
    return version;
}

export class TenantContextController {
    constructor({ documentRoot = document, channelFactory = null, navigate = null } = {}) {
        this.documentRoot = documentRoot;
        this.channelFactory = channelFactory ?? (() => (
            typeof BroadcastChannel === 'function' ? new BroadcastChannel('qmdb-tenant-context') : null
        ));
        this.navigate = navigate ?? ((path) => globalThis.location.assign(path));
        this.channel = null;
    }

    start() {
        this.channel = this.channelFactory();
        if (this.channel) this.channel.addEventListener('message', (event) => this.receive(event.data));
        this.documentRoot.addEventListener('qmdb:tenant-context-response', (event) => {
            const version = Number(event.detail?.tenant_context_version ?? 0);
            if (Number.isSafeInteger(version) && version > 0) this.publish(version);
        });
    }

    receive(message) {
        const version = message?.tenant_context_version;
        if (!message || message.type !== MESSAGE_TYPE || !Number.isSafeInteger(version)
            || version < 1 || version <= tenantContextVersion(this.documentRoot)) return;
        const EventConstructor = this.documentRoot.defaultView?.CustomEvent ?? CustomEvent;
        this.documentRoot.dispatchEvent(new EventConstructor('qmdb:tenant-context-stale', {
            detail: { tenant_context_version: version },
        }));
        this.navigate('/account/workspaces');
    }

    publish(version) {
        this.channel?.postMessage({ type: MESSAGE_TYPE, tenant_context_version: version });
    }
}
