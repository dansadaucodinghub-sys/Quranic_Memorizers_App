import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom('<!doctype html><html><body><select data-qmdb-theme><option value="system">System</option><option value="dark">Dark</option></select></body></html>');
const { ThemeController } = await import('../../public/assets/js/theme-controller.js');

test('applies approved stored theme and persists only approved theme key', () => {
    const writes = [];
    const storage = { getItem: () => 'dark', setItem: (...args) => writes.push(args) };
    const selector = document.querySelector('select');
    const controller = new ThemeController({ selector, storage, documentRef: document, liveRegion: { announce() {} }, media: null });
    controller.start();
    assert.equal(document.documentElement.dataset.theme, 'dark');
    controller.apply('system');
    assert.deepEqual(writes, [['qmdb.theme', 'system']]);
    assert.equal(controller.apply('malicious'), false);
});

test('handles unavailable browser storage', () => {
    const storage = { getItem: () => { throw new Error('blocked'); }, setItem: () => { throw new Error('blocked'); } };
    const controller = new ThemeController({ selector: null, storage, documentRef: document, liveRegion: null, media: null });
    assert.doesNotThrow(() => controller.start());
    assert.doesNotThrow(() => controller.apply('light'));
});
