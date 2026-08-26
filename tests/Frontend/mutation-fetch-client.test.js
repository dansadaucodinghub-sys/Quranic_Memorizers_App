import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom('<!doctype html><form method="post" action="/register"><input name="email" value="person@example.test"><input type="hidden" name="csrf_token" value="csrf-value"><input type="hidden" name="registration_submission_id" value="01991f93-0b42-7abc-8abc-1234567890ab" data-qmdb-idempotency-key></form>');
const { submitMutationForm } = await import('../../public/assets/js/mutation-fetch-client.js');

test('submits one same-origin URL-encoded mutation with CSRF and idempotency headers', async () => {
    let captured;
    const result = await submitMutationForm(document.querySelector('form'), { fetchImpl: async (url, options) => {
        captured = { url, options };
        return new Response('<section data-qmdb-fragment-root>Accepted</section>', {
            status: 202,
            headers: {
                'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
                'X-QMDB-Fragment': '1',
                'X-Request-ID': 'request_12345678',
            },
        });
    } });
    assert.equal(captured.url, 'http://localhost/register');
    assert.equal(captured.options.method, 'POST');
    assert.equal(captured.options.credentials, 'same-origin');
    assert.equal(captured.options.headers['X-QMDB-CSRF'], 'csrf-value');
    assert.equal(captured.options.headers['Idempotency-Key'], '01991f93-0b42-7abc-8abc-1234567890ab');
    assert.match(captured.options.body.toString(), /email=person%40example.test/);
    assert.equal(result.status, 202);
});

test('does not retry a failed mutation', async () => {
    let calls = 0;
    await assert.rejects(submitMutationForm(document.querySelector('form'), { fetchImpl: async () => {
        calls += 1;
        throw new Error('offline');
    } }));
    assert.equal(calls, 1);
});

test('rejects cross-origin form action before fetch', async () => {
    const form = document.querySelector('form');
    form.action = 'https://evil.example/register';
    let calls = 0;
    await assert.rejects(submitMutationForm(form, { fetchImpl: async () => { calls += 1; } }));
    assert.equal(calls, 0);
});
