import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';
import { RequestCoordinator } from '../../public/assets/js/request-coordinator.js';

test('partial refresh replaces only its target and preserves fallback href', async () => {
    installDom('<!doctype html><html lang="en"><body><p id="untouched">Keep</p><section id="system-status-card">Old</section><a id="refresh" href="/system/status" data-qmdb-refresh data-qmdb-refresh-target="#system-status-card">Refresh</a></body></html>');
    const { PartialRefreshController } = await import('../../public/assets/js/partial-refresh-controller.js');
    const announcements = [];
    const controller = new PartialRefreshController({
        coordinator: new RequestCoordinator(),
        liveRegion: { announce: (message) => announcements.push(message) },
        focusManager: { restoreAfterReplacement() {} },
        fetcher: async () => ({ fragment: new DOMParser().parseFromString('<section id="system-status-card" data-qmdb-fragment-root data-qmdb-refresh-root>New</section>', 'text/html').body.firstElementChild }),
        documentRef: document,
    });
    controller.start();
    document.getElementById('refresh').click();
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(document.getElementById('system-status-card').textContent, 'New');
    assert.equal(document.getElementById('untouched').textContent, 'Keep');
    assert.equal(document.getElementById('refresh').getAttribute('href'), '/system/status');
    assert.equal(announcements.length, 1);
});

test('partial refresh failure preserves existing content and announces safely', async () => {
    installDom('<!doctype html><html lang="en"><body><section id="card">Old</section><a id="refresh" href="/system/status" data-qmdb-refresh data-qmdb-refresh-target="#card">Refresh</a></body></html>');
    const { PartialRefreshController } = await import('../../public/assets/js/partial-refresh-controller.js');
    const announcements = [];
    const controller = new PartialRefreshController({ coordinator: new RequestCoordinator(), liveRegion: { announce: (message) => announcements.push(message) }, focusManager: {}, fetcher: async () => { throw new Error('private server failure'); }, documentRef: document });
    controller.start(); document.getElementById('refresh').click();
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(document.getElementById('card').textContent, 'Old');
    assert.equal(document.querySelector('[data-qmdb-refresh-error-for="card"]').getAttribute('role'), 'alert');
    assert.equal(announcements[0].includes('private server failure'), false);
});
