export class PersonIdentityResolutionController {
    constructor({ documentRef = document, windowRef = window } = {}) {
        this.document = documentRef;
        this.window = windowRef;
        this.dialog = documentRef.getElementById('qmdb-dialog');
        this.pendingConfirmation = null;
        this.clearPairingSecrets = this.clearPairingSecrets.bind(this);
        this.handleConfirmation = this.handleConfirmation.bind(this);
        this.clearConfirmation = this.clearConfirmation.bind(this);
    }

    start() {
        if (!this.document.querySelector('[data-qmdb-person-identity-resolution]')) return;
        this.window.addEventListener('pagehide', this.clearPairingSecrets, { once: true });
        this.document.addEventListener('click', this.handleConfirmation);
        this.dialog?.addEventListener('close', this.clearConfirmation);
    }

    clearPairingSecrets() {
        for (const region of this.document.querySelectorAll('[data-qmdb-pairing-code-once]')) {
            region.replaceChildren();
            region.hidden = true;
        }
    }

    handleConfirmation(event) {
        const submitter = event.target.closest?.('[data-qmdb-person-confirm]');
        if (!(submitter instanceof HTMLButtonElement) || !this.supportsConfirmation() || submitter.disabled) return;
        const form = submitter.closest('form');
        if (!(form instanceof HTMLFormElement)) return;

        event.preventDefault();
        this.pendingConfirmation = { form, submitter };
        const title = submitter.dataset.qmdbPersonConfirmTitle ?? '';
        const message = submitter.dataset.qmdbPersonConfirmMessage ?? '';
        const content = this.dialog.querySelector('[data-qmdb-modal-content]');
        const heading = this.dialog.querySelector('#qmdb-dialog-title');
        const fallback = this.dialog.querySelector('[data-qmdb-modal-fallback]');
        if (!(content instanceof HTMLElement) || !(heading instanceof HTMLElement) || !(fallback instanceof HTMLAnchorElement)) return;

        heading.textContent = title;
        fallback.hidden = true;
        content.replaceChildren();
        const paragraph = this.document.createElement('p');
        paragraph.textContent = message;
        const actions = this.document.createElement('div');
        actions.className = 'actions';
        const cancel = this.document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'button';
        cancel.textContent = submitter.dataset.qmdbPersonConfirmCancel ?? 'Cancel';
        cancel.addEventListener('click', () => this.dialog.close());
        const confirm = this.document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'button danger';
        confirm.textContent = submitter.dataset.qmdbPersonConfirmSubmit ?? submitter.textContent?.trim() ?? 'Confirm';
        confirm.addEventListener('click', () => {
            const pending = this.pendingConfirmation;
            this.dialog.close();
            pending?.form.requestSubmit(pending.submitter);
        });
        actions.append(cancel, confirm);
        content.append(paragraph, actions);
        this.dialog.showModal();
        confirm.focus();
    }

    clearConfirmation() {
        this.pendingConfirmation = null;
        const fallback = this.dialog?.querySelector('[data-qmdb-modal-fallback]');
        if (fallback instanceof HTMLAnchorElement) fallback.hidden = false;
    }

    supportsConfirmation() {
        return Boolean(globalThis.HTMLDialogElement && this.dialog?.showModal);
    }
}
