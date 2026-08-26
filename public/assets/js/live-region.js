export class LiveRegion {
    constructor(element) { this.element = element; this.timer = null; }
    announce(message) {
        if (!this.element || !message) return;
        clearTimeout(this.timer);
        this.element.textContent = '';
        this.timer = setTimeout(() => { this.element.textContent = message; }, 30);
    }
}
