import { FocusManager } from './focus-manager.js';
import { LiveRegion } from './live-region.js';
import { ModalController } from './modal-controller.js';
import { PartialRefreshController } from './partial-refresh-controller.js';
import { RequestCoordinator } from './request-coordinator.js';
import { ThemeController } from './theme-controller.js';
import { ProgressiveFormController } from './progressive-form-controller.js';
import { PasskeyLoginController } from './passkey-login-controller.js';
import { PasskeyRegistrationController } from './passkey-registration-controller.js';
import { PasskeyStepUpController } from './passkey-step-up-controller.js';
import { RecoveryCodeDisplayController } from './recovery-code-display-controller.js';
import { TenantContextController } from './tenant-context-controller.js';
import { startGeographyDependentSelects } from './geography-dependent-select.js';

function start() {
    const liveRegion = new LiveRegion(document.getElementById('qmdb-live-region'));
    const coordinator = new RequestCoordinator();
    const focusManager = new FocusManager();
    new ThemeController({ selector: document.querySelector('[data-qmdb-theme]'), liveRegion }).start();
    new PartialRefreshController({ coordinator, liveRegion, focusManager }).start();
    new ModalController({ coordinator, liveRegion, focusManager }).start();
    new ProgressiveFormController({ liveRegion, focusManager }).start();
    new PasskeyLoginController({ liveRegion }).start();
    new PasskeyRegistrationController({ liveRegion }).start();
    new PasskeyStepUpController({ liveRegion }).start();
    new RecoveryCodeDisplayController({ liveRegion }).start();
    new TenantContextController().start();
    startGeographyDependentSelects();
}

try { start(); } catch { console.error('QMDB progressive enhancement could not start.'); }
