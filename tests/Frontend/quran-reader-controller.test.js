import test from 'node:test';
import assert from 'node:assert/strict';
import { QuranReaderController } from '../../public/assets/js/quran-reader-controller.js';

test('QuranReaderController leaves normal navigation available when Fetch is unavailable', () => {
    const listeners = new Map();
    const documentRef = { addEventListener: (name, listener) => listeners.set(name, listener) };
    const controller = new QuranReaderController({ liveRegion: { announce() {} }, documentRef, windowRef: {} });
    controller.start();
    assert.equal(typeof listeners.get('click'), 'function');
    assert.equal(typeof listeners.get('submit'), 'function');
});
