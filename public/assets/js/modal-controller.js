import { QmdbFetchError, fetchFragment } from './fetch-client.js';

export class ModalController {
    constructor({ coordinator, liveRegion, focusManager, fetcher = fetchFragment, documentRef = document }) {
        Object.assign(this, { coordinator, liveRegion, focusManager, fetcher, documentRef });
        this.dialog = documentRef.getElementById('qmdb-dialog');
        this.onClick = this.onClick.bind(this);
        this.onClose = this.onClose.bind(this);
    }
    supported() { return Boolean(globalThis.fetch && globalThis.AbortController && globalThis.HTMLDialogElement && this.dialog?.showModal); }
    start() {
        if (!this.dialog) return;
        this.documentRef.addEventListener('click', this.onClick);
        this.dialog.addEventListener('close', this.onClose);
        this.dialog.addEventListener('cancel', () => this.coordinator.abort('modal'));
    }
    async onClick(event) {
        const close = event.target.closest?.('[data-qmdb-modal-close]');
        if (close && this.dialog?.open) { this.dialog.close(); return; }
        const trigger = event.target.closest?.('[data-qmdb-modal]');
        if (!(trigger instanceof HTMLAnchorElement) || !this.supported() || this.dialog.open) return;
        event.preventDefault();
        this.focusManager.record(trigger);
        const loading = this.dialog.querySelector('[data-qmdb-modal-loading]');
        const errorRegion = this.dialog.querySelector('[data-qmdb-modal-error]');
        const content = this.dialog.querySelector('[data-qmdb-modal-content]');
        const fallback = this.dialog.querySelector('[data-qmdb-modal-fallback]');
        loading.hidden = false; errorRegion.hidden = true; content.replaceChildren(); fallback.href = trigger.href;
        this.dialog.showModal(); this.focusManager.focus(this.dialog.querySelector('[data-qmdb-modal-close]'));
        const request = this.coordinator.begin('modal');
        try {
            const result = await this.fetcher(trigger.href, { locale: this.documentRef.documentElement.lang, signal: request.signal });
            if (!request.isCurrent() || !this.dialog.open) return;
            const fragment = this.documentRef.importNode(result.fragment, true);
            const title = fragment.dataset.qmdbDialogTitle;
            if (title) this.dialog.querySelector('#qmdb-dialog-title').textContent = title;
            content.replaceChildren(fragment); loading.hidden = true;
            this.focusManager.focus(content.querySelector('a,button,[tabindex="0"]') ?? this.dialog.querySelector('[data-qmdb-modal-close]'));
        } catch (error) {
            if (error?.name === 'AbortError' || !request.isCurrent()) return;
            loading.hidden = true; errorRegion.hidden = false;
            const message = this.documentRef.createElement('p');
            message.textContent = this.documentRef.documentElement.lang === 'ar' ? 'تعذر تحميل المحتوى المطلوب.' : 'The requested content could not be loaded.';
            errorRegion.replaceChildren(message);
            if (error instanceof QmdbFetchError && error.requestId) {
                const reference = this.documentRef.createElement('p'); reference.textContent = `Request reference: ${error.requestId}`; errorRegion.append(reference);
            }
            this.focusManager.focus(errorRegion); this.liveRegion.announce(message.textContent);
        } finally { request.complete(); }
    }
    onClose() {
        this.coordinator.abort('modal');
        this.dialog.querySelector('[data-qmdb-modal-content]').replaceChildren();
        this.dialog.querySelector('[data-qmdb-modal-error]').replaceChildren();
        this.dialog.querySelector('[data-qmdb-modal-error]').hidden = true;
        this.dialog.querySelector('#qmdb-dialog-title').textContent = this.documentRef.documentElement.lang === 'ar' ? 'تفاصيل النظام' : 'System details';
        this.focusManager.restore();
    }
}
