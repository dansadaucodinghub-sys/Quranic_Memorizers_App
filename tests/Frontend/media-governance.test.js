import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { installDom } from './test-dom.js';
import { ProgressiveFormController } from '../../public/assets/js/progressive-form-controller.js';

function mediaForm() {
    installDom('<!doctype html><html lang="ar"><body><section data-qmdb-form-region>' +
        '<div data-qmdb-error-summary hidden tabindex="-1"></div>' +
        '<form method="post" action="/workspace/media/019c0000-0000-7000-8000-000000000001/hold" data-qmdb-progressive-form>' +
        '<input name="csrf_token" value="test-csrf">' +
        '<input name="submission_id" value="019c0000-0000-7000-8000-000000000002" data-qmdb-idempotency-key>' +
        '<input name="workspace_id" value="019c0000-0000-7000-8000-000000000003">' +
        '<input name="tenant_context_version" value="4"><input name="version" value="3">' +
        '<input name="reason_code" value="INDEPENDENT_REVIEW"><input name="hold_code" value="CONSENT">' +
        '<button type="submit">Confirm</button></form></section></body></html>');
    return document.querySelector('form');
}

function fragment(html, status = 200) {
    return new Response(html, { status, headers: {
        'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
        'X-QMDB-Fragment': '1',
    } });
}

test('media form templates provide the existing security and accessibility hooks', async () => {
    const source = await readFile(new URL('../../resources/views/fragments/media-governance.php', import.meta.url), 'utf8');
    for (const hook of ['data-qmdb-idempotency-key', 'data-qmdb-error-summary', 'data-qmdb-completion-heading', 'data-qmdb-fragment-root', 'name="workspace_id"', 'name="tenant_context_version"']) {
        assert.ok(source.includes(hook), hook);
    }
    assert.ok(source.includes('method="post"'));
    assert.ok(source.includes('for="reason-code"'));
});

test('media mutations send CSRF and idempotency, suppress duplicate clicks and focus authoritative success', async () => {
    const form = mediaForm();
    let calls = 0;
    let complete;
    let focused;
    globalThis.fetch = (url, options) => {
        calls += 1;
        assert.match(url, /\/hold$/);
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.redirect, 'error');
        assert.equal(options.headers['X-QMDB-CSRF'], 'test-csrf');
        assert.equal(options.headers['Idempotency-Key'], form.elements.submission_id.value);
        assert.equal(options.headers['X-QMDB-Locale'], 'ar');
        assert.equal(options.body.get('workspace_id'), form.elements.workspace_id.value);
        assert.equal(options.body.get('tenant_context_version'), '4');
        return new Promise((resolve) => { complete = resolve; });
    };
    const controller = new ProgressiveFormController({
        focusManager: { focus: (target) => { focused = target; } },
        liveRegion: { announce() {} },
    });
    const first = controller.handleSubmit({ target: form, preventDefault() {} });
    await controller.handleSubmit({ target: form, preventDefault() {} });
    assert.equal(calls, 1);
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(form.querySelector('button').disabled, true);
    complete(fragment('<section data-qmdb-fragment-root data-qmdb-form-region><p role="status" data-qmdb-completion-heading tabindex="-1">Hold recorded</p></section>'));
    await first;
    assert.equal(focused.textContent, 'Hold recorded');
    assert.equal(window.localStorage.length, 0);
    assert.equal(window.sessionStorage.length, 0);
});

test('conflict fragments preserve the form workflow and focus the error summary', async () => {
    const form = mediaForm();
    let focused;
    let announcement;
    globalThis.fetch = async () => fragment('<section data-qmdb-fragment-root data-qmdb-form-region><div role="alert" data-qmdb-error-summary tabindex="-1">Reload this changed asset.</div><form method="post" action="/workspace/media/019c0000-0000-7000-8000-000000000001/hold" data-qmdb-progressive-form><input type="hidden" name="csrf_token" value="test-csrf"><input type="hidden" name="submission_id" data-qmdb-idempotency-key value="019c0000-0000-7000-8000-000000000002"><button type="submit">Review</button></form></section>', 409);
    const controller = new ProgressiveFormController({
        focusManager: { focus: (target) => { focused = target; } },
        liveRegion: { announce: (message) => { announcement = message; } },
    });
    await controller.handleSubmit({ target: form, preventDefault() {} });
    assert.equal(focused.getAttribute('role'), 'alert');
    assert.ok(document.querySelector('form'));
    assert.equal(announcement, 'Review the form errors.');
});

test('uncertain media mutations are never automatically replayed', async () => {
    const form = mediaForm();
    let calls = 0;
    globalThis.fetch = async () => { calls += 1; throw new TypeError('Synthetic network failure'); };
    const controller = new ProgressiveFormController({ focusManager: { focus() {} }, liveRegion: { announce() {} } });
    await controller.handleSubmit({ target: form, preventDefault() {} });
    assert.equal(calls, 1);
    assert.equal(form.isConnected, true);
    assert.equal(form.querySelector('button').disabled, false);
    assert.equal(form.hasAttribute('aria-busy'), false);
    assert.equal(form.elements.submission_id.value, '019c0000-0000-7000-8000-000000000002');
});

test('untrusted mutation responses do not replace the governance form', async () => {
    const form = mediaForm();
    globalThis.fetch = async () => new Response('<script>unsafe()</script>', { headers: { 'Content-Type': 'text/html' } });
    const controller = new ProgressiveFormController({ focusManager: { focus() {} }, liveRegion: { announce() {} } });
    await controller.handleSubmit({ target: form, preventDefault() {} });
    assert.equal(form.isConnected, true);
    assert.equal(document.querySelector('script'), null);
});
