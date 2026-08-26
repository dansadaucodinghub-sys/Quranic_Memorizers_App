export class RequestCoordinator {
    #active = new Map();

    begin(key) {
        this.#active.get(key)?.controller.abort();
        const previous = this.#active.get(key)?.sequence ?? 0;
        const entry = { controller: new AbortController(), sequence: previous + 1 };
        this.#active.set(key, entry);
        return {
            signal: entry.controller.signal,
            sequence: entry.sequence,
            isCurrent: () => this.#active.get(key) === entry,
            complete: () => { if (this.#active.get(key) === entry) this.#active.delete(key); },
            abort: () => entry.controller.abort(),
        };
    }

    abort(key) {
        this.#active.get(key)?.controller.abort();
        this.#active.delete(key);
    }
}
