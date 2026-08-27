import { parseSafeFragment } from './fragment-policy.js';
import { QmdbFetchError } from './fetch-client.js';

const FRAGMENT_MEDIA_TYPE = 'text/vnd.qmdb.fragment+html';

function safeNavigation(response, base) {
    const value = (response.headers.get('X-QMDB-Navigate') ?? '').trim();
    if (!value) return '';
    if (!value.startsWith('/') || value.startsWith('//') || /[\u0000-\u001f\u007f]/.test(value)) {
        throw new QmdbFetchError({ code: 'UNSAFE_NAVIGATION_REJECTED', title: 'Unsafe navigation rejected' });
    }
    const resolved = new URL(value, base);
    if (resolved.origin !== new URL(base).origin) {
        throw new QmdbFetchError({ code: 'UNSAFE_NAVIGATION_REJECTED', title: 'Unsafe navigation rejected' });
    }
    return `${resolved.pathname}${resolved.search}${resolved.hash}`;
}

export async function submitMutationForm(form, { signal, fetchImpl = globalThis.fetch } = {}) {
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
        throw new TypeError('Only POST forms can be progressively submitted.');
    }
    const base = globalThis.location?.href ?? 'http://localhost/';
    const target = new URL(form.action, base);
    if (target.origin !== new URL(base).origin) {
        throw new QmdbFetchError({ code: 'CROSS_ORIGIN_REJECTED', title: 'Cross-origin request rejected' });
    }
    const body = new URLSearchParams(new FormData(form));
    const csrf = body.get('csrf_token');
    const idempotency = form.querySelector('[data-qmdb-idempotency-key]')?.value ?? '';
    const headers = {
        Accept: `${FRAGMENT_MEDIA_TYPE}, application/problem+json`,
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-QMDB-CSRF': typeof csrf === 'string' ? csrf : '',
    };
    if (idempotency) headers['Idempotency-Key'] = idempotency;
    let response;
    try {
        response = await fetchImpl(target.href, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            signal,
            headers,
            body,
        });
    } catch (error) {
        if (error?.name === 'AbortError') throw error;
        throw new QmdbFetchError();
    }
    const requestId = response.headers.get('X-Request-ID') ?? '';
    const navigate = response.ok ? safeNavigation(response, base) : '';
    const contentType = (response.headers.get('Content-Type') ?? '').toLowerCase();
    if (contentType.startsWith(FRAGMENT_MEDIA_TYPE) && response.headers.get('X-QMDB-Fragment') === '1') {
        const fragment = parseSafeFragment(await response.text(), base);
        return { fragment, requestId, status: response.status, ok: response.ok, navigate };
    }
    let problem = {};
    if (contentType.startsWith('application/problem+json')) {
        try { problem = await response.json(); } catch { problem = {}; }
    }
    throw new QmdbFetchError({
        status: response.status,
        requestId: requestId || (typeof problem.request_id === 'string' ? problem.request_id : ''),
        code: typeof problem.code === 'string' ? problem.code : 'MUTATION_REQUEST_FAILED',
        title: typeof problem.title === 'string' ? problem.title : 'Request failed',
        retryable: false,
    });
}

export async function submitJsonMutation(
    path,
    { csrfToken, body, signal, fetchImpl = globalThis.fetch } = {},
) {
    const base = globalThis.location?.href ?? 'http://localhost/';
    const target = new URL(path, base);
    if (target.origin !== new URL(base).origin) {
        throw new QmdbFetchError({ code: 'CROSS_ORIGIN_REJECTED', title: 'Cross-origin request rejected' });
    }
    let response;
    try {
        response = await fetchImpl(target.href, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            signal,
            headers: {
                Accept: 'application/json, application/problem+json',
                'Content-Type': 'application/json',
                'X-QMDB-CSRF': csrfToken ?? '',
            },
            body: JSON.stringify(body ?? {}),
        });
    } catch (error) {
        if (error?.name === 'AbortError') throw error;
        throw new QmdbFetchError();
    }
    const requestId = response.headers.get('X-Request-ID') ?? '';
    let payload = {};
    try { payload = await response.json(); } catch { payload = {}; }
    if (!response.ok) {
        throw new QmdbFetchError({
            status: response.status,
            requestId: requestId || (typeof payload.request_id === 'string' ? payload.request_id : ''),
            code: typeof payload.code === 'string' ? payload.code : 'MUTATION_REQUEST_FAILED',
            title: typeof payload.title === 'string' ? payload.title : 'Request failed',
            retryable: false,
        });
    }

    return payload;
}
