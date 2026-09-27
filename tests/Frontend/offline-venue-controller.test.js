import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://qmdb.test/workspace/offline-devices' });
globalThis.document = dom.window.document;
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
});
