export class RecoveryCodeDisplayController {
    constructor({ clipboard = globalThis.navigator?.clipboard, liveRegion } = {}) {
        this.clipboard = clipboard;
        this.liveRegion = liveRegion;
    }

    start(root = document) {
        for (const region of root.querySelectorAll('[data-qmdb-recovery-code-display]')) {
            const button = region.querySelector('[data-qmdb-copy-recovery-codes]');
            if (!button || typeof this.clipboard?.writeText !== 'function') {
                if (button) button.hidden = true;
                continue;
            }
            button.addEventListener('click', () => this.#copy(region, button));
        }
    }

    async #copy(region, button) {
        if (button.disabled) return;
        const codes = [...region.querySelectorAll('[data-qmdb-recovery-code-values] code')]
            .map((element) => element.textContent?.trim())
            .filter(Boolean);
        if (codes.length === 0) return;
        button.disabled = true;
        try {
            await this.clipboard.writeText(codes.join('\n'));
            this.liveRegion?.announce?.('Recovery codes copied.');
        } catch {
            this.liveRegion?.announce?.('Recovery codes could not be copied.');
        } finally {
            button.disabled = false;
        }
    }
}
