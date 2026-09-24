import { submitMutationForm } from './mutation-fetch-client.js';

const COMMUNITY_PATH = /^\/(?:community(?:\/|$)|account\/community(?:\/|$)|workspace\/community(?:\/|$)|clips\/[^/]+\/(?:comments|interactions)(?:\/|$))/;

function controls(form) {
    return form.querySelectorAll('button[type="submit"], input[type="submit"]');
}

export class CommunityFormController {
    constructor({ liveRegion, focusManager, documentRoot = document, submit = submitMutationForm }) {
        this.liveRegion = liveRegion;
        this.focusManager = focusManager;
        this.documentRoot = documentRoot;
        this.submit = submit;
        this.active = new WeakMap();
        this.handleSubmit = this.handleSubmit.bind(this);
    }

    start() {
        this.documentRoot.addEventListener('submit', this.handleSubmit);
    }

    async handleSubmit(event) {
        const form = event.target instanceof HTMLFormElement ? event.target.closest('form') : null;
        if (!form || form.method.toLowerCase() !== 'post' || typeof this.submit !== 'function') return;
        const base = globalThis.location?.href ?? 'http://localhost/';
        const target = new URL(form.action, base);
        if (target.origin !== new URL(base).origin || !COMMUNITY_PATH.test(target.pathname)) return;
        event.preventDefault();
        if (this.active.has(form)) return;
        const controller = new AbortController();
        this.active.set(form, controller);
        form.setAttribute('aria-busy', 'true');
        for (const control of controls(form)) control.disabled = true;
        try {
            const result = await this.submit(form, { signal: controller.signal });
            if (result.navigate) {
                globalThis.location.assign(result.navigate);
                this.liveRegion.announce('Request completed.');
                return;
            }
            if (!result.fragment) throw new TypeError('Community fragment response is missing.');
            const current = form.closest('[data-qmdb-fragment-root]');
            if (!current) throw new TypeError('Community fragment target is unavailable.');
            current.replaceWith(result.fragment);
            const focus = result.fragment.querySelector('[role="alert"]') ?? result.fragment;
            this.focusManager.focus(focus);
            this.liveRegion.announce(result.ok ? 'Request completed.' : 'Review the form errors.');
        } catch (error) {
            if (error?.name === 'AbortError') return;
            if (error?.navigate && ['TENANT_CONTEXT_STALE', 'TENANT_CONTEXT_REQUIRED'].includes(error?.code)) {
                globalThis.location.assign(error.navigate);
                this.liveRegion.announce('Workspace context changed. Review the active workspace.');
                return;
            }
            const reference = error?.requestId ? ` Request reference: ${error.requestId}` : '';
            this.liveRegion.announce(`The request could not be completed.${reference}`);
            this.focusManager.focus(form.closest('[data-qmdb-fragment-root]')?.querySelector('[role="alert"]'));
        } finally {
            this.active.delete(form);
            if (form.isConnected) {
                form.removeAttribute('aria-busy');
                for (const control of controls(form)) control.disabled = false;
            }
        }
    }
}
