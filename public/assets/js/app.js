import { FocusManager } from './focus-manager.js';
import { LiveRegion } from './live-region.js';
import { ModalController } from './modal-controller.js';
import { PartialRefreshController } from './partial-refresh-controller.js';
import { RequestCoordinator } from './request-coordinator.js';
import { ThemeController } from './theme-controller.js';

function start() {
    const liveRegion = new LiveRegion(document.getElementById('qmdb-live-region'));
    const coordinator = new RequestCoordinator();
    const focusManager = new FocusManager();
    new ThemeController({ selector: document.querySelector('[data-qmdb-theme]'), liveRegion }).start();
    new PartialRefreshController({ coordinator, liveRegion, focusManager }).start();
    new ModalController({ coordinator, liveRegion, focusManager }).start();
}

try { start(); } catch { console.error('QMDB progressive enhancement could not start.'); }
