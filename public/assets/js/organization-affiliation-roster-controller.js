export class OrganizationAffiliationRosterController {
    constructor({ documentRef = document } = {}) {
        this.documentRef = documentRef;
        this.onChange = this.onChange.bind(this);
    }

    start() {
        this.documentRef.addEventListener('change', this.onChange);
    }

    onChange(event) {
        const filter = event.target instanceof HTMLSelectElement
            ? event.target.closest('select[data-qmdb-affiliation-status-filter]')
            : null;
        if (!filter || !globalThis.fetch || !globalThis.AbortController) return;
        const refresh = filter.form?.parentElement?.querySelector('a[data-qmdb-affiliation-refresh]')
            ?? this.documentRef.querySelector('a[data-qmdb-affiliation-refresh]');
        if (!(refresh instanceof HTMLAnchorElement)) return;
        const url = new URL(refresh.href, globalThis.location?.href ?? 'http://localhost/');
        url.searchParams.set('status', filter.value);
        refresh.href = `${url.pathname}${url.search}`;
        refresh.click();
    }
}
