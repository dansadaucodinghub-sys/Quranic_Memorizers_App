import { parseSafeFragment } from './fragment-policy.js';

export class GeographyDependentSelectController {
    constructor({ parent, target, fetchImpl = globalThis.fetch, announce = null, messages = {} }) {
        this.parent = parent;
        this.target = target;
        this.fetchImpl = fetchImpl;
        this.announce = announce;
        this.messages = {
            loading: messages.loading ?? 'Loading areas',
            loaded: messages.loaded ?? 'Areas loaded.',
            unavailable: messages.unavailable ?? 'Unable to load areas.',
            cleared: messages.cleared ?? 'No area selected.',
        };
        this.abortController = null;
        this.requestNumber = 0;
        this.onChange = this.onChange.bind(this);
    }

    start() {
        if (!(this.parent instanceof HTMLSelectElement) || !(this.target instanceof HTMLElement)
            || typeof this.fetchImpl !== 'function' || typeof AbortController !== 'function') return;
        this.parent.addEventListener('change', this.onChange);
        if (this.parent.value) void this.onChange();
    }

    async onChange() {
        const parentId = this.parent.value;
        if (this.abortController) this.abortController.abort();
        const requestNumber = ++this.requestNumber;
        if (!parentId) {
            this.clear();
            return;
        }
        const controller = new AbortController();
        this.abortController = controller;
        this.setBusy(true, this.messages.loading);
        try {
            const url = new URL('/lookups/geography/children', globalThis.location.origin);
            url.searchParams.set('parent', parentId);
            if (this.parent.dataset.qmdbGeographyChildField) {
                url.searchParams.set('field', this.parent.dataset.qmdbGeographyChildField);
            }
            if (this.parent.dataset.qmdbGeographySelectedChild) {
                url.searchParams.set('selected', this.parent.dataset.qmdbGeographySelectedChild);
            }
            const response = await this.fetchImpl(`${url.pathname}${url.search}`, {
                method: 'GET',
                headers: { Accept: 'text/vnd.qmdb.fragment+html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (requestNumber !== this.requestNumber || controller.signal.aborted) return;
            if (!response.ok || !response.headers.get('Content-Type')?.toLowerCase().startsWith('text/vnd.qmdb.fragment+html')
                || response.headers.get('X-QMDB-Fragment') !== '1') throw new TypeError('Geography children response is invalid.');
            const fragment = parseSafeFragment(await response.text(), globalThis.location.href);
            if (requestNumber !== this.requestNumber || controller.signal.aborted) return;
            this.target.replaceWith(fragment);
            this.target = fragment;
            this.announce?.(this.messages.loaded);
        } catch (error) {
            if (controller.signal.aborted || requestNumber !== this.requestNumber) return;
            this.showError();
            this.announce?.(this.messages.unavailable);
        } finally {
            if (requestNumber === this.requestNumber) this.setBusy(false, '');
        }
    }

    clear() {
        this.target.replaceChildren();
        this.target.setAttribute('aria-busy', 'false');
        this.announce?.(this.messages.cleared);
    }

    setBusy(value, message) {
        this.target.setAttribute('aria-busy', value ? 'true' : 'false');
        if (message) this.announce?.(message);
    }

    showError() {
        this.target.replaceChildren();
        const message = document.createElement('p');
        message.setAttribute('role', 'status');
        message.textContent = this.messages.unavailable;
        this.target.append(message);
    }
}

export function startGeographyDependentSelects(documentRef = document) {
    for (const parent of documentRef.querySelectorAll('[data-qmdb-geography-level-one]')) {
        const targetSelector = parent.getAttribute('data-qmdb-geography-child-target');
        const target = targetSelector ? documentRef.querySelector(targetSelector) : null;
        if (!(parent instanceof HTMLSelectElement) || !(target instanceof HTMLElement)) continue;
        new GeographyDependentSelectController({
            parent,
            target,
            messages: {
                loading: parent.dataset.qmdbGeographyLoadingMessage,
                loaded: parent.dataset.qmdbGeographyLoadedMessage,
                unavailable: parent.dataset.qmdbGeographyErrorMessage,
                cleared: parent.dataset.qmdbGeographyClearedMessage,
            },
            announce: (message) => {
                const live = documentRef.getElementById('qmdb-live-region');
                if (live) live.textContent = message;
            },
        }).start();
    }
}
