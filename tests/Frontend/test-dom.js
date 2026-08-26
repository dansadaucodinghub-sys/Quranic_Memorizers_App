import { JSDOM } from 'jsdom';

export function installDom(html = '<!doctype html><html lang="en"><body><div id="qmdb-live-region"></div></body></html>', url = 'http://localhost/') {
    const dom = new JSDOM(html, { url });
    for (const name of ['window', 'document', 'location', 'DOMParser', 'Element', 'HTMLElement', 'HTMLAnchorElement', 'HTMLDialogElement', 'CSS']) {
        globalThis[name] = dom.window[name];
    }
    if (!globalThis.CSS?.escape) globalThis.CSS = { escape: (value) => value.replace(/[^A-Za-z0-9_-]/g, '\\$&') };
    return dom;
}
