export const THEMES = ['system', 'light', 'dark', 'high-contrast', 'emerald-gold'];

export class ThemeController {
    constructor({ selector, liveRegion, storage = globalThis.localStorage, documentRef = document, media = globalThis.matchMedia?.('(prefers-color-scheme: dark)') }) {
        Object.assign(this, { selector, liveRegion, storage, documentRef, media });
    }
    start() {
        let value = 'system';
        try { const stored = this.storage?.getItem('qmdb.theme'); if (THEMES.includes(stored)) value = stored; } catch { value = 'system'; }
        this.apply(value, false);
        this.selector?.addEventListener('change', () => this.apply(this.selector.value, true));
        this.media?.addEventListener?.('change', () => { if (this.documentRef.documentElement.dataset.theme === 'system') this.apply('system', false); });
    }
    apply(theme, persist = true) {
        if (!THEMES.includes(theme)) return false;
        this.documentRef.documentElement.dataset.theme = theme;
        if (this.selector) this.selector.value = theme;
        if (persist) { try { this.storage?.setItem('qmdb.theme', theme); } catch { /* preference storage is optional */ } }
        if (persist) {
            const label = this.selector?.selectedOptions?.[0]?.textContent?.trim() || theme;
            const prefix = this.documentRef.documentElement.lang === 'ar' ? 'تم تغيير المظهر إلى' : 'Theme changed to';
            this.liveRegion?.announce(`${prefix} ${label}.`);
        }
        return true;
    }
}
