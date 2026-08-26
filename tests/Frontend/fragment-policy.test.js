import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom();
const { parseSafeFragment } = await import('../../public/assets/js/fragment-policy.js');

test('accepts one safe localized fragment root', () => {
    const root = parseSafeFragment('<section data-qmdb-fragment-root><h2>حالة النظام</h2><a href="/system/status">Safe</a></section>');
    assert.equal(root.tagName, 'SECTION');
});

for (const [name, markup] of [
    ['script', '<section data-qmdb-fragment-root><script></script></section>'],
    ['style', '<section data-qmdb-fragment-root><style></style></section>'],
    ['iframe', '<section data-qmdb-fragment-root><iframe></iframe></section>'],
    ['inline event', '<section data-qmdb-fragment-root><button onclick="x()">x</button></section>'],
    ['javascript URL', '<section data-qmdb-fragment-root><a href="javascript:x()">x</a></section>'],
    ['mutation form', '<section data-qmdb-fragment-root><form method="post"></form></section>'],
    ['multiple roots', '<section data-qmdb-fragment-root></section><section data-qmdb-fragment-root></section>'],
    ['nested modal', '<section data-qmdb-fragment-root><dialog id="qmdb-dialog"></dialog></section>'],
]) test(`rejects ${name}`, () => assert.throws(() => parseSafeFragment(markup), TypeError));

test('accepts an approved same-origin registration mutation form', () => {
    const root = parseSafeFragment('<section data-qmdb-fragment-root><form method="post" action="/register" data-qmdb-progressive-form><input type="hidden" name="csrf_token"><input type="hidden" name="registration_submission_id" data-qmdb-idempotency-key></form></section>');
    assert.equal(root.querySelector('form').method, 'post');
});

for (const [name, markup] of [
    ['cross-origin mutation', '<section data-qmdb-fragment-root><form method="post" action="https://evil.example" data-qmdb-progressive-form><input type="hidden" name="csrf_token"></form></section>'],
    ['file mutation', '<section data-qmdb-fragment-root><form method="post" action="/register" data-qmdb-progressive-form><input type="hidden" name="csrf_token"><input type="hidden" data-qmdb-idempotency-key><input type="file"></form></section>'],
    ['missing CSRF', '<section data-qmdb-fragment-root><form method="post" action="/verify-email/id" data-qmdb-progressive-form></form></section>'],
    ['missing idempotency', '<section data-qmdb-fragment-root><form method="post" action="/register" data-qmdb-progressive-form><input type="hidden" name="csrf_token"></form></section>'],
    ['formaction override', '<section data-qmdb-fragment-root><form method="post" action="/verify-email/id" data-qmdb-progressive-form><input type="hidden" name="csrf_token"><button formaction="/other"></button></form></section>'],
]) test(`rejects ${name}`, () => assert.throws(() => parseSafeFragment(markup), TypeError));
