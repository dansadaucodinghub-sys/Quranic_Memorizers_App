import test from 'node:test';
import assert from 'node:assert/strict';
import { CommunityFormController } from '../../public/assets/js/community-form-controller.js';
import { installDom } from './test-dom.js';

test('community form delegates to the approved mutation client and locks duplicate submission', async () => {
    const dom = installDom('<html lang="en"><body><section data-qmdb-fragment-root><form method="post" action="/account/community/appeals/case"><input name="csrf_token" value="csrf"><input name="submission_id" value="submission" data-qmdb-idempotency-key><button type="submit">Submit</button></form></section></body></html>', 'http://localhost/account/community/appeals');
    const calls = [];
    let complete;
    const pending = new Promise((resolve) => { complete = resolve; });
    const controller = new CommunityFormController({
        liveRegion: { announce() {} }, focusManager: { focus() {} }, documentRoot: document,
        submit: async (form, options) => {
            calls.push({ form, options });
            await pending;
            const template = document.createElement('template');
            template.innerHTML = '<section data-qmdb-fragment-root><p role="alert">Invalid</p></section>';
            return { fragment: template.content.firstElementChild, ok: false, status: 422, navigate: '' };
        },
    });
    const form = document.querySelector('form');
    const event = { target: form, preventDefault() {} };
    const first = controller.handleSubmit(event);
    const second = controller.handleSubmit(event);
    complete();
    await Promise.all([first, second]);
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(calls.length, 1);
    assert.equal(calls[0].form, form);
    assert.ok(calls[0].options.signal instanceof AbortSignal);
    dom.window.close();
});

test('community form ignores cross-origin and unrelated mutations', async () => {
    const dom = installDom('<form method="post" action="https://example.invalid/community"><button type="submit">Submit</button></form>', 'http://localhost/');
    let prevented = false;
    const controller = new CommunityFormController({
        liveRegion: { announce() {} }, focusManager: { focus() {} }, documentRoot: document,
        submit: async () => { throw new Error('must not submit'); },
    });
    await controller.handleSubmit({ target: document.querySelector('form'), preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    dom.window.close();
});
