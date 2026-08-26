import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom();
const { fetchFragment, QmdbFetchError } = await import('../../public/assets/js/fetch-client.js');

test('sends same-origin fragment request with locale and credentials', async () => {
    let request;
    const result = await fetchFragment('/system/about', { locale: 'ar', fetchImpl: async (url, options) => {
        request = { url, options };
        return new Response('<section data-qmdb-fragment-root>آمن</section>', { headers: { 'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8', 'X-QMDB-Fragment': '1', 'X-Request-ID': 'request_12345678' } });
    } });
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers['X-QMDB-Locale'], 'ar');
    assert.equal(result.requestId, 'request_12345678');
});

test('rejects cross-origin URL before fetch', async () => {
    await assert.rejects(fetchFragment('https://evil.example/x'), QmdbFetchError);
});

test('parses safe problem details without retaining raw HTML', async () => {
    await assert.rejects(fetchFragment('/x', { fetchImpl: async () => new Response(JSON.stringify({ title: 'Unavailable', request_id: 'request_abcdefgh' }), { status: 503, headers: { 'Content-Type': 'application/problem+json' } }) }), (error) => {
        assert.equal(error.requestId, 'request_abcdefgh');
        assert.equal(Object.hasOwn(error, 'html'), false);
        return true;
    });
});

test('rejects an invalid fragment content type', async () => {
    await assert.rejects(fetchFragment('/x', { fetchImpl: async () => new Response('not a fragment', { headers: { 'Content-Type': 'text/html', 'X-QMDB-Fragment': '1' } }) }), QmdbFetchError);
});

test('rejects a fragment without the response marker', async () => {
    await assert.rejects(fetchFragment('/x', { fetchImpl: async () => new Response('<section data-qmdb-fragment-root>Safe</section>', { headers: { 'Content-Type': 'text/vnd.qmdb.fragment+html' } }) }), QmdbFetchError);
});
