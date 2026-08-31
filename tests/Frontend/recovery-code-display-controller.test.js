import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { installDom } from './test-dom.js';

installDom();
const { RecoveryCodeDisplayController } = await import('../../public/assets/js/recovery-code-display-controller.js');

test('copies visible codes only through the clipboard without persistence or network access', async () => {
    document.body.innerHTML = `
        <section data-qmdb-recovery-code-display>
            <ul data-qmdb-recovery-code-values><li><code>AAAA-BBBB</code></li><li><code>CCCC-DDDD</code></li></ul>
            <button data-qmdb-copy-recovery-codes>Copy</button>
        </section>`;
    const writes = [];
    const messages = [];
    new RecoveryCodeDisplayController({
        clipboard: { writeText: async (value) => writes.push(value) },
        liveRegion: { announce: (value) => messages.push(value) },
    }).start(document);

    document.querySelector('[data-qmdb-copy-recovery-codes]').click();
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.deepEqual(writes, ['AAAA-BBBB\nCCCC-DDDD']);
    assert.deepEqual(messages, ['Recovery codes copied.']);
    const controllerSource = await readFile(
        new URL('../../public/assets/js/recovery-code-display-controller.js', import.meta.url),
        'utf8',
    );
    for (const forbiddenClientSink of ['localStorage', 'sessionStorage', 'document.cookie', 'fetch(']) {
        assert.equal(controllerSource.includes(forbiddenClientSink), false);
    }
});

test('hides the copy control when the clipboard API is unavailable', () => {
    document.body.innerHTML = `
        <section data-qmdb-recovery-code-display>
            <ul data-qmdb-recovery-code-values><li><code>AAAA-BBBB</code></li></ul>
            <button data-qmdb-copy-recovery-codes>Copy</button>
        </section>`;
    new RecoveryCodeDisplayController({ clipboard: null }).start(document);

    assert.equal(document.querySelector('[data-qmdb-copy-recovery-codes]').hidden, true);
});
