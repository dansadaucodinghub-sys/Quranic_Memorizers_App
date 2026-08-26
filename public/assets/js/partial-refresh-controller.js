import { QmdbFetchError, fetchFragment } from './fetch-client.js';

export class PartialRefreshController {
    constructor({ coordinator, liveRegion, focusManager, fetcher = fetchFragment, documentRef = document }) {
        Object.assign(this, { coordinator, liveRegion, focusManager, fetcher, documentRef });
        this.onClick = this.onClick.bind(this);
    }
    start() { this.documentRef.addEventListener('click', this.onClick); }
    async onClick(event) {
        const trigger = event.target.closest?.('[data-qmdb-refresh]');
        if (!(trigger instanceof HTMLAnchorElement) || !globalThis.fetch || !globalThis.AbortController) return;
        const selector = trigger.dataset.qmdbRefreshTarget;
        if (!selector || !/^#[A-Za-z][A-Za-z0-9_-]*$/.test(selector)) return;
        const target = this.documentRef.querySelector(selector);
        if (!target) return;
        event.preventDefault();
        this.documentRef.querySelector(`[data-qmdb-refresh-error-for="${selector.slice(1)}"]`)?.remove();
        const request = this.coordinator.begin(selector);
        const focusKey = this.documentRef.activeElement?.dataset?.qmdbFocusKey ?? '';
        target.setAttribute('aria-busy', 'true');
        trigger.setAttribute('aria-disabled', 'true');
        try {
            const result = await this.fetcher(trigger.href, { locale: this.documentRef.documentElement.lang, signal: request.signal });
            if (!request.isCurrent()) return;
            const replacement = this.documentRef.importNode(result.fragment, true);
            target.replaceWith(replacement);
            this.focusManager.restoreAfterReplacement(replacement, focusKey);
            this.liveRegion.announce(this.documentRef.documentElement.lang === 'ar' ? 'تم تحديث حالة النظام.' : 'System status refreshed.');
        } catch (error) {
            if (error?.name !== 'AbortError' && request.isCurrent()) {
                const reference = error instanceof QmdbFetchError && error.requestId ? ` (${error.requestId})` : '';
                const message = (this.documentRef.documentElement.lang === 'ar'
                    ? 'تعذر تحديث الحالة'
                    : 'Status could not be refreshed') + reference;
                const errorElement = this.documentRef.createElement('p');
                errorElement.className = 'async-error';
                errorElement.dataset.qmdbRefreshErrorFor = selector.slice(1);
                errorElement.setAttribute('role', 'alert');
                errorElement.textContent = message;
                target.insertAdjacentElement('afterend', errorElement);
                this.liveRegion.announce(message);
            }
        } finally {
            if (request.isCurrent()) {
                target.removeAttribute('aria-busy');
                trigger.removeAttribute('aria-disabled');
            }
            request.complete();
        }
    }
}
