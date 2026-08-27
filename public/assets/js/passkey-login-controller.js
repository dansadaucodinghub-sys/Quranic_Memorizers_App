import { WebAuthnClient } from './webauthn-client.js';

export class PasskeyLoginController {
    constructor({ client = new WebAuthnClient(), liveRegion } = {}) {
        this.client = client;
        this.liveRegion = liveRegion;
    }

    start(root = document) {
        for (const button of root.querySelectorAll('[data-qmdb-passkey-login]')) {
            button.hidden = !this.client.supported();
            button.addEventListener('click', () => this.#authenticate(button), { once: false });
        }
    }

    async #authenticate(button) {
        if (button.disabled) return;
        button.disabled = true;
        try {
            const result = await this.client.authenticate({
                optionsUrl: button.dataset.optionsUrl,
                verifyUrl: button.dataset.verifyUrl,
                csrfToken: button.dataset.csrfToken,
            });
            if (typeof result.navigate === 'string') window.location.assign(result.navigate);
        } catch (error) {
            this.liveRegion?.announce?.(error.reference ? `Passkey failed. Request ${error.reference}` : 'Passkey verification failed.');
        } finally {
            button.disabled = false;
        }
    }
}
