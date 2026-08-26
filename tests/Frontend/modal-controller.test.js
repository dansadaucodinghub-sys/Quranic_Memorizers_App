import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';
import { RequestCoordinator } from '../../public/assets/js/request-coordinator.js';

function modalMarkup() {
    return '<!doctype html><html lang="en"><body><a id="open" href="/system/about" data-qmdb-modal>Open</a><dialog id="qmdb-dialog" aria-labelledby="qmdb-dialog-title"><h2 id="qmdb-dialog-title">System details</h2><button data-qmdb-modal-close>Close</button><div data-qmdb-modal-loading hidden></div><div data-qmdb-modal-error hidden></div><div data-qmdb-modal-content></div><a data-qmdb-modal-fallback href="/system/about">Fallback</a></dialog></body></html>';
}

test('modal loads one safe fragment and restores focus on close', async () => {
    installDom(modalMarkup());
    const dialog = document.getElementById('qmdb-dialog');
    dialog.showModal = () => dialog.setAttribute('open', '');
    dialog.close = () => { dialog.removeAttribute('open'); dialog.dispatchEvent(new window.Event('close')); };
    const { ModalController } = await import('../../public/assets/js/modal-controller.js');
    let trigger;
    const focusManager = { record: (value) => { trigger = value; }, focus() {}, restore: () => trigger.focus() };
    const controller = new ModalController({ coordinator: new RequestCoordinator(), liveRegion: { announce() {} }, focusManager, fetcher: async () => ({ fragment: new DOMParser().parseFromString('<section data-qmdb-fragment-root data-qmdb-dialog-title="About"><p>Safe</p></section>', 'text/html').body.firstElementChild }), documentRef: document });
    controller.start(); document.getElementById('open').click();
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(dialog.hasAttribute('open'), true);
    assert.equal(dialog.querySelector('[data-qmdb-modal-content]').textContent, 'Safe');
    assert.equal(dialog.querySelector('#qmdb-dialog-title').textContent, 'About');
    dialog.querySelector('[data-qmdb-modal-close]').click();
    assert.equal(document.activeElement.id, 'open');
});

test('open dialog prevents a nested modal request', async () => {
    installDom(modalMarkup());
    const dialog = document.getElementById('qmdb-dialog'); dialog.setAttribute('open', ''); dialog.showModal = () => {};
    const { ModalController } = await import('../../public/assets/js/modal-controller.js');
    let calls = 0;
    const controller = new ModalController({ coordinator: new RequestCoordinator(), liveRegion: { announce() {} }, focusManager: { record() {}, focus() {}, restore() {} }, fetcher: async () => { calls += 1; }, documentRef: document });
    controller.start();
    document.getElementById('open').addEventListener('click', (event) => event.preventDefault(), { capture: true });
    document.getElementById('open').click(); await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(calls, 0);
});

test('modal failure stays open and shows a safe request reference with fallback', async () => {
    installDom(modalMarkup());
    const dialog = document.getElementById('qmdb-dialog');
    dialog.showModal = () => dialog.setAttribute('open', '');
    const { ModalController } = await import('../../public/assets/js/modal-controller.js');
    const { QmdbFetchError } = await import('../../public/assets/js/fetch-client.js');
    const controller = new ModalController({ coordinator: new RequestCoordinator(), liveRegion: { announce() {} }, focusManager: { record() {}, focus() {}, restore() {} }, fetcher: async () => { throw new QmdbFetchError({ requestId: 'request_abcdefgh' }); }, documentRef: document });
    controller.start(); document.getElementById('open').click(); await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(dialog.hasAttribute('open'), true);
    assert.match(dialog.querySelector('[data-qmdb-modal-error]').textContent, /request_abcdefgh/);
    assert.equal(dialog.querySelector('[data-qmdb-modal-fallback]').href.endsWith('/system/about'), true);
});
