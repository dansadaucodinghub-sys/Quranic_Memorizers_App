import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { installDom } from './test-dom.js';

const dom = installDom();
const { PersonIdentityResolutionController } = await import('../../public/assets/js/person-identity-resolution-controller.js');

test('one-time pairing code is removed from the document when its page is hidden', () => {
    document.body.innerHTML = `<section data-qmdb-person-identity-resolution><section data-qmdb-pairing-code-once><code>QMPC-AAAAAAAAAAAA-secret</code></section></section>`;
    const listeners = new Map();
    const windowRef = { addEventListener: (type, handler) => listeners.set(type, handler) };
    new PersonIdentityResolutionController({ documentRef: dom.window.document, windowRef }).start();

    listeners.get('pagehide')();
    const region = document.querySelector('[data-qmdb-pairing-code-once]');
    assert.equal(region.hidden, true);
    assert.equal(region.textContent, '');
});

test('a destructive profile action is confirmed in the accessible dialog before its form submits', () => {
    document.body.innerHTML = `
        <dialog id="qmdb-dialog"><h2 id="qmdb-dialog-title"></h2><div data-qmdb-modal-content></div><a data-qmdb-modal-fallback href="/fallback">Fallback</a></dialog>
        <section data-qmdb-person-identity-resolution><form><button type="submit" data-qmdb-person-confirm data-qmdb-person-confirm-title="Revoke claim?" data-qmdb-person-confirm-message="This prevents acceptance." data-qmdb-person-confirm-cancel="Keep claim" data-qmdb-person-confirm-submit="Revoke claim">Revoke claim</button></form></section>`;
    const dialog = document.getElementById('qmdb-dialog');
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; dialog.dispatchEvent(new dom.window.Event('close')); };
    const listeners = new Map();
    const windowRef = { addEventListener: (type, handler) => listeners.set(type, handler) };
    const controller = new PersonIdentityResolutionController({ documentRef: dom.window.document, windowRef });
    controller.start();

    const form = document.querySelector('form');
    let submitted = false;
    form.requestSubmit = () => { submitted = true; };
    const action = form.querySelector('button');
    action.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true }));

    assert.equal(dialog.open, true);
    assert.equal(dialog.querySelector('#qmdb-dialog-title').textContent, 'Revoke claim?');
    assert.equal(submitted, false);
    dialog.querySelector('[data-qmdb-modal-content] button.button.danger').click();
    assert.equal(submitted, true);
});

test('identity-resolution controller has no browser-storage, URL, or network sink for pairing material', async () => {
    const source = await readFile(new URL('../../public/assets/js/person-identity-resolution-controller.js', import.meta.url), 'utf8');

    assert.equal(source.includes('localStorage'), false);
    assert.equal(source.includes('sessionStorage'), false);
    assert.equal(source.includes('fetch('), false);
    assert.equal(source.includes('window.location'), false);
    assert.match(source, /pagehide/);
    assert.match(source, /data-qmdb-pairing-code-once/);
});

test('pairing code removal leaves no readable secret text or client-side replacement state', () => {
    document.body.innerHTML = `<section data-qmdb-person-identity-resolution><section data-qmdb-pairing-code-once><code>QMPC-ABCDEF123456-0123456789abcdefghij_-AB</code></section></section>`;
    const listeners = new Map();
    const windowRef = { addEventListener: (type, handler) => listeners.set(type, handler) };
    new PersonIdentityResolutionController({ documentRef: dom.window.document, windowRef }).start();

    listeners.get('pagehide')();
    const region = document.querySelector('[data-qmdb-pairing-code-once]');
    assert.equal(region.hidden, true);
    assert.equal(region.textContent, '');
    assert.equal(document.body.textContent.includes('QMPC-'), false);
    assert.equal(document.querySelector('[data-qmdb-pairing-code-once] code'), null);
});
