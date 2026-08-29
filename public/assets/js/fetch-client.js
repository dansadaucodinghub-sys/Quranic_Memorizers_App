import { parseSafeFragment } from './fragment-policy.js';

export class QmdbFetchError extends Error {
    constructor({ status = 0, code = 'FRAGMENT_REQUEST_FAILED', requestId = '', title = 'Request failed', retryable = false, navigate = '' } = {}) {
        super(title);
        this.name = 'QmdbFetchError';
        this.status = status;
        this.code = code;
        this.requestId = /^[A-Za-z0-9_-]{8,128}$/.test(requestId) ? requestId : '';
        this.title = title;
        this.retryable = retryable;
        this.navigate = typeof navigate === 'string' && navigate.startsWith('/') && !navigate.startsWith('//')
            ? navigate
            : '';
    }
}

export async function fetchFragment(url, { locale = 'en', signal, fetchImpl = globalThis.fetch } = {}) {
    const base = globalThis.location?.href ?? 'http://localhost/';
    const target = new URL(url, base);
    if (target.origin !== new URL(base).origin) {
        throw new QmdbFetchError({ code: 'CROSS_ORIGIN_REJECTED', title: 'Cross-origin request rejected' });
    }
    let response;
    try {
        response = await fetchImpl(target.href, {
            method: 'GET',
            credentials: 'same-origin',
            redirect: 'error',
            signal,
            headers: { Accept: 'text/vnd.qmdb.fragment+html', 'X-QMDB-Locale': locale },
        });
    } catch (error) {
        if (error?.name === 'AbortError') throw error;
        throw new QmdbFetchError();
    }
    const headerRequestId = response.headers.get('X-Request-ID') ?? '';
    if (!response.ok) {
        let problem = {};
        if ((response.headers.get('Content-Type') ?? '').toLowerCase().startsWith('application/problem+json')) {
            try { problem = await response.json(); } catch { problem = {}; }
        }
        throw new QmdbFetchError({
            status: response.status,
            code: typeof problem.code === 'string' ? problem.code : 'FRAGMENT_REQUEST_FAILED',
            requestId: headerRequestId || (typeof problem.request_id === 'string' ? problem.request_id : ''),
            title: typeof problem.title === 'string' && problem.title.length <= 120 ? problem.title : 'Request failed',
            retryable: response.status >= 500,
        });
    }
    const contentType = (response.headers.get('Content-Type') ?? '').toLowerCase();
    if (!contentType.startsWith('text/vnd.qmdb.fragment+html') || response.headers.get('X-QMDB-Fragment') !== '1') {
        throw new QmdbFetchError({ status: response.status, requestId: headerRequestId, code: 'INVALID_FRAGMENT', title: 'Invalid response' });
    }
    const markup = await response.text();
    return { fragment: parseSafeFragment(markup, base), requestId: headerRequestId };
}
