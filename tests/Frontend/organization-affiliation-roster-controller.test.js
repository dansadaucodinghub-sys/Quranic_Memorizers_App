import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

test('organization-affiliation status selection retains a no-JavaScript form and refreshes the private roster target', async () => {
    installDom('<!doctype html><html><body><main><form method="get"><select data-qmdb-affiliation-status-filter name="status"><option value="ALL">All</option><option value="ACTIVE">Active</option></select><button type="submit">Filter</button></form><a data-qmdb-affiliation-refresh data-qmdb-refresh href="/workspace/organizations/organization/affiliations?status=ALL">Refresh roster</a></main></body></html>');
    globalThis.fetch = async () => new Response('');
    const { OrganizationAffiliationRosterController } = await import('../../public/assets/js/organization-affiliation-roster-controller.js');
    let refreshed = 0;
    document.querySelector('[data-qmdb-affiliation-refresh]').addEventListener('click', (event) => { event.preventDefault(); refreshed += 1; });
    new OrganizationAffiliationRosterController({ documentRef: document }).start();
    const select = document.querySelector('select');
    select.value = 'ACTIVE';
    select.dispatchEvent(new window.Event('change', { bubbles: true }));

    assert.equal(document.querySelector('form').method.toLowerCase(), 'get');
    assert.equal(document.querySelector('[data-qmdb-affiliation-refresh]').getAttribute('href'), '/workspace/organizations/organization/affiliations?status=ACTIVE');
    assert.equal(refreshed, 1);
});
