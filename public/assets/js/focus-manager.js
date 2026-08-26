export class FocusManager {
    constructor() { this.trigger = null; }
    record(element) { this.trigger = element instanceof HTMLElement ? element : null; }
    focus(element) { if (element instanceof HTMLElement && element.isConnected) element.focus({ preventScroll: true }); }
    restore() { this.focus(this.trigger); this.trigger = null; }
    restoreAfterReplacement(root, key) {
        if (!key || !(root instanceof Element)) return;
        this.focus(root.querySelector(`[data-qmdb-focus-key="${CSS.escape(key)}"]`));
    }
}
