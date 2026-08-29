import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom('<!doctype html><meta name="qmdb-tenant-context-version" content="3"><form method="post" action="/register"><input name="email" value="person@example.test"><input type="hidden" name="csrf_token" value="csrf-value"><input type="hidden" name="registration_submission_id" value="01991f93-0b42-7abc-8abc-1234567890ab" data-qmdb-idempotency-key></form>');
const { submitJsonMutation, submitMutationForm } = await import('../../public/assets/js/mutation-fetch-client.js');

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
    assert.equal(captured.options.headers['X-QMDB-Tenant-Context-Version'], '3');
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

test('accepts a safe same-origin enhanced navigation instruction', async () => {
    const form = document.querySelector('form');
    form.action = '/login';
    const result = await submitMutationForm(form, { fetchImpl: async () => new Response(
        '<section data-qmdb-fragment-root>Signed in</section>',
        {
            status: 200,
            headers: {
                'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
                'X-QMDB-Fragment': '1',
                'X-QMDB-Navigate': '/account/security/sessions',
            },
        },
    ) });

    assert.equal(result.navigate, '/account/security/sessions');
});

test('rejects an unsafe enhanced navigation instruction', async () => {
    const form = document.querySelector('form');
    form.action = '/login';
    await assert.rejects(
        submitMutationForm(form, { fetchImpl: async () => new Response(
            '<section data-qmdb-fragment-root>Signed in</section>',
            {
                status: 200,
                headers: {
                    'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8',
                    'X-QMDB-Fragment': '1',
                    'X-QMDB-Navigate': '//evil.example/session',
                },
            },
        ) }),
        (error) => error?.code === 'UNSAFE_NAVIGATION_REJECTED',
    );
});

test('submits same-origin JSON mutations once with CSRF and no retry', async () => {
    let calls = 0;
    const result = await submitJsonMutation('/login/passkey/options', {
        csrfToken: 'csrf-json',
        body: { purpose: 'login' },
        fetchImpl: async (url, options) => {
            calls += 1;
            assert.equal(url, 'http://localhost/login/passkey/options');
            assert.equal(options.method, 'POST');
            assert.equal(options.credentials, 'same-origin');
            assert.equal(options.redirect, 'error');
            assert.equal(options.headers['X-QMDB-CSRF'], 'csrf-json');
            assert.equal(options.headers['X-QMDB-Tenant-Context-Version'], '3');
            return new Response('{"status":"ok"}', {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        },
    });
    assert.equal(calls, 1);
    assert.deepEqual(result, { status: 'ok' });
});

test('rejects cross-origin JSON mutation before fetch', async () => {
    let calls = 0;
    await assert.rejects(submitJsonMutation('https://evil.example/options', {
        csrfToken: 'csrf-json',
        body: {},
        fetchImpl: async () => { calls += 1; },
    }));
    assert.equal(calls, 0);
});

test('surfaces stale tenant context once with safe navigation and no retry', async () => {
    let calls = 0;
    await assert.rejects(submitJsonMutation('/account/workspaces/switch', {
        csrfToken: 'csrf-json',
        body: { workspace_id: '01991f93-0b42-7abc-8abc-1234567890ab', tenant_context_version: 2 },
        fetchImpl: async () => {
            calls += 1;
            return new Response(JSON.stringify({
                code: 'TENANT_CONTEXT_STALE',
                title: 'Conflict',
                status: 409,
                request_id: 'request_12345678',
            }), {
                status: 409,
                headers: {
                    'Content-Type': 'application/problem+json',
                    'X-QMDB-Navigate': '/account/workspaces',
                },
            });
        },
    }), (error) => error?.code === 'TENANT_CONTEXT_STALE'
        && error?.navigate === '/account/workspaces' && error?.requestId === 'request_12345678');
    assert.equal(calls, 1);
});
