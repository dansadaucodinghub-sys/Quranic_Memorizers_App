import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

function deferred() {
    let resolve;
    const promise = new Promise((next) => { resolve = next; });
    return { promise, resolve };
}

test('uses a fragment-only same-origin GET and safely replaces the child region', async () => {
    installDom('<!doctype html><html><body><div id="qmdb-live-region"></div><select id="parent"></select><section id="target"></section></body></html>');
    const { GeographyDependentSelectController } = await import('../../public/assets/js/geography-dependent-select.js');
    const parent = document.getElementById('parent');
    const target = document.getElementById('target');
    parent.innerHTML = '<option value=""></option><option value="c234787d-c2a4-4d5a-989d-d5e0d3b88013">FCT</option>';
    parent.value = 'c234787d-c2a4-4d5a-989d-d5e0d3b88013';
    let options;
    const controller = new GeographyDependentSelectController({
        parent,
        target,
        fetchImpl: async (url, requestOptions) => {
            options = { url, requestOptions };
            return new Response('<section id="target" data-qmdb-fragment-root><select><option>AMAC</option></select></section>', {
                status: 200,
                headers: { 'Content-Type': 'text/vnd.qmdb.fragment+html; charset=utf-8', 'X-QMDB-Fragment': '1' },
            });
        },
    });
    controller.start();
    await controller.onChange();

    assert.match(options.url, /^\/lookups\/geography\/children\?parent=/);
    assert.equal(options.requestOptions.method, 'GET');
    assert.equal(options.requestOptions.headers.Accept, 'text/vnd.qmdb.fragment+html');
    assert.equal(options.requestOptions.credentials, 'same-origin');
    assert.equal(document.querySelector('#target option').textContent, 'AMAC');
});

test('aborts a stale lookup and keeps the newest response authoritative', async () => {
    installDom('<!doctype html><html><body><select id="parent"></select><section id="target"></section></body></html>');
    const { GeographyDependentSelectController } = await import('../../public/assets/js/geography-dependent-select.js');
    const parent = document.getElementById('parent');
    parent.innerHTML = '<option value=""></option><option value="c234787d-c2a4-4d5a-989d-d5e0d3b88013">FCT</option><option value="5cc845f8-57d8-4d6d-afd6-c84ba4f6367f">Abia</option>';
    const first = deferred();
    const second = deferred();
    let calls = 0;
    const controller = new GeographyDependentSelectController({
        parent,
        target: document.getElementById('target'),
        fetchImpl: () => (++calls === 1 ? first.promise : second.promise),
    });
    controller.start();
    parent.value = 'c234787d-c2a4-4d5a-989d-d5e0d3b88013';
    const firstRequest = controller.onChange();
    parent.value = '5cc845f8-57d8-4d6d-afd6-c84ba4f6367f';
    const secondRequest = controller.onChange();
    second.resolve(new Response('<section id="target" data-qmdb-fragment-root><p>Newest</p></section>', {
        headers: { 'Content-Type': 'text/vnd.qmdb.fragment+html', 'X-QMDB-Fragment': '1' },
    }));
    first.resolve(new Response('<section id="target" data-qmdb-fragment-root><p>Stale</p></section>', {
        headers: { 'Content-Type': 'text/vnd.qmdb.fragment+html', 'X-QMDB-Fragment': '1' },
    }));
    await Promise.all([firstRequest, secondRequest]);

    assert.equal(calls, 2);
    assert.equal(document.querySelector('#target p').textContent, 'Newest');
});
