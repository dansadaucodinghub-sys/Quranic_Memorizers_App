import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

const { ProgressiveFormController } = await import('../../public/assets/js/progressive-form-controller.js');

function formDocument() {
    installDom(`<!doctype html><html><body>
        <section data-qmdb-form-region>
            <div data-qmdb-error-summary hidden tabindex="-1"></div>
            <form method="post" action="/register" data-qmdb-progressive-form>
                <input name="email" value="person@example.test">
                <input name="password" type="password" value="Secret passphrase 123!">
                <input name="csrf_token" value="csrf-value">
                <button type="submit">Submit</button>
            </form>
        </section>
    </body></html>`);
    return document.querySelector('form');
}

test('blocks duplicate submissions and focuses the completed fragment', async () => {
    const form = formDocument();
    let resolveFetch;
    let calls = 0;
    globalThis.fetch = () => {
        calls += 1;
        return new Promise((resolve) => { resolveFetch = resolve; });
    };
    let focused;
    let announcement;
    const controller = new ProgressiveFormController({
        focusManager: { focus: (element) => { focused = element; } },
        liveRegion: { announce: (message) => { announcement = message; } },
    });
    const first = controller.handleSubmit({ target: form, preventDefault() {} });
    const duplicate = controller.handleSubmit({ target: form, preventDefault() {} });
    assert.equal(calls, 1);
    assert.equal(form.getAttribute('aria-busy'), 'true');
    resolveFetch(new Response(
        '<section data-qmdb-fragment-root><h1 data-qmdb-completion-heading tabindex="-1">Done</h1></section>',
        {
            status: 202,
            headers: {
                'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
                'X-QMDB-Fragment': '1',
            },
        },
    ));
    await Promise.all([first, duplicate]);
    assert.equal(focused?.textContent, 'Done');
    assert.equal(announcement, 'Request completed.');
    assert.equal(document.querySelector('form'), null);
});

test('clears password fields and announces request references on failure', async () => {
    const form = formDocument();
    globalThis.fetch = async () => new Response(JSON.stringify({
        title: 'Request failed',
        request_id: 'request_12345678',
    }), {
        status: 503,
        headers: { 'Content-Type': 'application/problem+json' },
    });
    let focused;
    let announcement;
    const controller = new ProgressiveFormController({
        focusManager: { focus: (element) => { focused = element; } },
        liveRegion: { announce: (message) => { announcement = message; } },
    });
    await controller.handleSubmit({ target: form, preventDefault() {} });

    assert.equal(form.querySelector('input[type="password"]').value, '');
    assert.equal(focused, document.querySelector('[data-qmdb-error-summary]'));
    assert.match(announcement, /request_12345678/);
    assert.equal(form.hasAttribute('aria-busy'), false);
    assert.equal(form.querySelector('button').disabled, false);
});

test('replaces the approved account-security panel and closes a revoke modal only on success', async () => {
    installDom(`<!doctype html><html><body>
        <h1 id="account-security-heading" tabindex="-1">Security</h1>
        <section id="account-security-session-panel">Old inventory</section>
        <dialog open><section data-qmdb-form-region>
            <form method="post" action="/account/security/sessions/01991f93-0b42-7abc-8abc-1234567890ab/revoke" data-qmdb-progressive-form data-qmdb-success-target="#account-security-session-panel" data-qmdb-close-modal-on-success="true">
                <input name="csrf_token" value="csrf-value">
                <input name="expected_version" value="2">
                <button type="submit">Revoke</button>
            </form>
        </section></dialog>
    </body></html>`);
    const form = document.querySelector('form');
    const dialog = document.querySelector('dialog');
    let closed = false;
    dialog.close = () => { closed = true; };
    globalThis.fetch = async () => new Response(
        '<section id="account-security-session-panel" data-qmdb-fragment-root>Updated inventory</section>',
        {
            status: 200,
            headers: {
                'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
                'X-QMDB-Fragment': '1',
            },
        },
    );
    let focused;
    const controller = new ProgressiveFormController({
        focusManager: { focus: (element) => { focused = element; } },
        liveRegion: { announce() {} },
    });
    await controller.handleSubmit({ target: form, preventDefault() {} });

    assert.equal(document.querySelector('#account-security-session-panel').textContent, 'Updated inventory');
    assert.equal(closed, true);
    assert.equal(focused, document.querySelector('#account-security-heading'));
});

test('keeps the revoke modal open when the mutation fails', async () => {
    installDom(`<!doctype html><html><body><dialog open><section data-qmdb-form-region>
        <div data-qmdb-error-summary tabindex="-1"></div>
        <form method="post" action="/account/security/devices/01991f93-0b42-7abc-8abc-1234567890ab/revoke" data-qmdb-progressive-form data-qmdb-success-target="#account-security-session-panel" data-qmdb-close-modal-on-success="true">
            <input name="csrf_token" value="csrf-value"><input name="expected_version" value="2">
            <button type="submit">Revoke</button>
        </form>
    </section></dialog></body></html>`);
    const form = document.querySelector('form');
    const dialog = document.querySelector('dialog');
    let closed = false;
    dialog.close = () => { closed = true; };
    globalThis.fetch = async () => new Response(JSON.stringify({ title: 'Conflict' }), {
        status: 409,
        headers: { 'Content-Type': 'application/problem+json' },
    });
    const controller = new ProgressiveFormController({
        focusManager: { focus() {} },
        liveRegion: { announce() {} },
    });
    await controller.handleSubmit({ target: form, preventDefault() {} });

    assert.equal(closed, false);
    assert.equal(dialog.hasAttribute('open'), true);
});
