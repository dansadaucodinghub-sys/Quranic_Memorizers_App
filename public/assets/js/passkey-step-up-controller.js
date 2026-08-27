import { PasskeyLoginController } from './passkey-login-controller.js';

export class PasskeyStepUpController extends PasskeyLoginController {
    start(root = document) {
        for (const button of root.querySelectorAll('[data-qmdb-passkey-step-up]')) {
            button.setAttribute('data-qmdb-passkey-login', '');
        }
        super.start(root);
    }
}
