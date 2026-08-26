const PROHIBITED = 'script,style,iframe,object,embed,applet,base,link,dialog#qmdb-dialog';
const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction'];

export function parseSafeFragment(markup, baseUrl = globalThis.location?.href ?? 'http://localhost/') {
    const document = new DOMParser().parseFromString(markup, 'text/html');
    if (document.querySelector(PROHIBITED) || document.querySelector('meta[http-equiv="refresh" i]')) {
        throw new TypeError('Fragment contains prohibited content.');
    }
    const roots = [...document.body.children];
    if (roots.length !== 1 || !roots[0].hasAttribute('data-qmdb-fragment-root')) {
        throw new TypeError('Fragment must contain one approved root.');
    }
    for (const element of document.body.querySelectorAll('*')) {
        for (const attribute of [...element.attributes]) {
            if (attribute.name.toLowerCase().startsWith('on')) {
                throw new TypeError('Fragment contains an event attribute.');
            }
            if (URL_ATTRIBUTES.includes(attribute.name.toLowerCase())) {
                const value = attribute.value.trim();
                if (/^(?:javascript|data):/i.test(value) || value.startsWith('//')) {
                    throw new TypeError('Fragment contains an unsafe URL.');
                }
                if ((attribute.name === 'action' || attribute.name === 'formaction') && value) {
                    const resolved = new URL(value, baseUrl);
                    if (resolved.origin !== new URL(baseUrl).origin) {
                        throw new TypeError('Fragment contains a cross-origin form action.');
                    }
                }
            }
        }
    }
    for (const form of document.querySelectorAll('form')) {
        const method = (form.getAttribute('method') ?? 'get').toLowerCase();
        if (method === 'get') continue;
        const action = (form.getAttribute('action') ?? '').trim();
        const approved = method === 'post'
            && form.hasAttribute('data-qmdb-progressive-form')
            && action.startsWith('/')
            && !action.startsWith('//')
            && !form.querySelector('input[type="file"], [formaction]')
            && form.querySelector('input[type="hidden"][name="csrf_token"]');
        if (!approved) {
            throw new TypeError('Fragment contains a mutation form.');
        }
        if ((action === '/register' || action === '/verify-email/resend' || action === '/login')
            && !form.querySelector('[data-qmdb-idempotency-key]')) {
            throw new TypeError('Fragment mutation form lacks idempotency protection.');
        }
        if (/^\/account\/security\/(?:sessions|devices)\/[^/]+\/revoke$/.test(action)
            && !form.querySelector('input[type="hidden"][name="expected_version"]')) {
            throw new TypeError('Fragment revocation form lacks optimistic concurrency protection.');
        }
    }
    return roots[0];
}
