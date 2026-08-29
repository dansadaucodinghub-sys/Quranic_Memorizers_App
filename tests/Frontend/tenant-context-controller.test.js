import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

const { adoptTenantContextResponse, TenantContextController, tenantContextVersion } = await import(
    '../../public/assets/js/tenant-context-controller.js'
);

test('reads only the server-rendered context version and adopts response versions', () => {
    installDom('<!doctype html><meta name="qmdb-tenant-context-version" content="3">');
    window.localStorage.clear();
    window.sessionStorage.clear();
    assert.equal(tenantContextVersion(), 3);
    assert.equal(adoptTenantContextResponse(new Response('', {
        headers: { 'X-QMDB-Tenant-Context-Version': '4' },
    })), 4);
    assert.equal(tenantContextVersion(), 4);
    assert.equal(window.localStorage.length, 0);
    assert.equal(window.sessionStorage.length, 0);
    document.querySelector('meta').content = '9007199254740993';
    assert.equal(tenantContextVersion(), 0);
});

test('broadcasts and receives only bounded version messages without workspace authority', () => {
    installDom('<!doctype html><meta name="qmdb-tenant-context-version" content="2">');
    const sent = [];
    const navigated = [];
    let receive;
    const controller = new TenantContextController({ channelFactory: () => ({
        addEventListener: (_type, listener) => { receive = listener; },
        postMessage: (message) => sent.push(message),
    }), navigate: (path) => navigated.push(path) });
    let staleVersion = 0;
    document.addEventListener('qmdb:tenant-context-stale', (event) => {
        staleVersion = event.detail.tenant_context_version;
    });
    controller.start();
    controller.publish(3);
    assert.deepEqual(sent, [{ type: 'tenant-context-version', tenant_context_version: 3 }]);
    receive({ data: {
        type: 'tenant-context-version',
        tenant_context_version: 5,
        workspace_id: 'ignored',
    } });
    assert.equal(tenantContextVersion(), 2);
    assert.equal(staleVersion, 5);
    assert.deepEqual(navigated, ['/account/workspaces']);
});
