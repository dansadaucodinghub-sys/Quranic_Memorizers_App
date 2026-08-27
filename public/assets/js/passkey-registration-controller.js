import { WebAuthnClient } from './webauthn-client.js';

export class PasskeyRegistrationController {
    constructor({ client = new WebAuthnClient(), liveRegion } = {}) {
        this.client = client;
        this.liveRegion = liveRegion;
    }

    start(root = document) {
        for (const form of root.querySelectorAll('[data-qmdb-passkey-registration]')) {
            form.hidden = !this.client.supported();
            form.addEventListener('submit', (event) => this.#register(event, form));
        }
    }

    async #register(event, form) {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button?.disabled) return;
        if (button) button.disabled = true;
        try {
            const result = await this.client.register({
                optionsUrl: form.dataset.optionsUrl,
                verifyUrl: form.dataset.verifyUrl,
                csrfToken: form.dataset.csrfToken,
                displayName: new FormData(form).get('display_name'),
            });
            if (typeof result.navigate === 'string') window.location.assign(result.navigate);
        } catch (error) {
            this.liveRegion?.announce?.(error.reference ? `Passkey failed. Request ${error.reference}` : 'Passkey registration failed.');
        } finally {
            if (button) button.disabled = false;
        }
    }
}
