import { submitMutationForm } from './mutation-fetch-client.js';

export class ProgressiveFormController {
    constructor({ liveRegion, focusManager, documentRoot = document }) {
        this.liveRegion = liveRegion;
        this.focusManager = focusManager;
        this.documentRoot = documentRoot;
        this.busy = new WeakSet();
        this.handleSubmit = this.handleSubmit.bind(this);
    }

    start() {
        this.documentRoot.addEventListener('submit', this.handleSubmit);
    }

    async handleSubmit(event) {
        const form = event.target instanceof HTMLFormElement
            ? event.target.closest('form[data-qmdb-progressive-form]')
            : null;
        if (!form || typeof fetch !== 'function' || this.busy.has(form)) return;
        event.preventDefault();
        this.busy.add(form);
        form.setAttribute('aria-busy', 'true');
        for (const control of form.querySelectorAll('button[type="submit"], input[type="submit"]')) {
            control.disabled = true;
        }
        try {
            const result = await submitMutationForm(form);
            if (result.navigate) {
                globalThis.location.assign(result.navigate);
                this.liveRegion.announce('Request completed.');
                return;
            }
            const successTarget = form.dataset.qmdbSuccessTarget ?? '';
            if (result.ok && successTarget) {
                if (successTarget !== '#account-security-session-panel' || !result.fragment.matches(successTarget)) {
                    throw new TypeError('Approved success target is invalid.');
                }
                const existing = this.documentRoot.querySelector(successTarget);
                if (!existing) throw new TypeError('Approved success target is missing.');
                existing.replaceWith(result.fragment);
                if (form.dataset.qmdbCloseModalOnSuccess === 'true') form.closest('dialog')?.close();
                this.focusManager.focus(this.documentRoot.querySelector('#account-security-heading'));
                this.liveRegion.announce('Request completed.');
                return;
            }
            const region = form.closest('[data-qmdb-form-region]');
            if (!region) throw new TypeError('Approved form region is missing.');
            region.replaceWith(result.fragment);
            const target = result.fragment.querySelector('[data-qmdb-error-summary]:not([hidden])')
                ?? result.fragment.querySelector('[data-qmdb-completion-heading]')
                ?? result.fragment;
            this.focusManager.focus(target);
            this.liveRegion.announce(result.ok ? 'Request completed.' : 'Review the form errors.');
        } catch (error) {
            const reference = error?.requestId ? ` Request reference: ${error.requestId}` : '';
            this.liveRegion.announce(`The request could not be completed.${reference}`);
            for (const password of form.querySelectorAll('input[type="password"]')) password.value = '';
            const summary = form.closest('[data-qmdb-form-region]')?.querySelector('[data-qmdb-error-summary]');
            this.focusManager.focus(summary);
        } finally {
            this.busy.delete(form);
            if (form.isConnected) {
                form.removeAttribute('aria-busy');
                for (const control of form.querySelectorAll('button[type="submit"], input[type="submit"]')) {
                    control.disabled = false;
                }
            }
        }
    }
}
