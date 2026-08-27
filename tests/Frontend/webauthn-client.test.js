import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom } from './test-dom.js';

installDom();
const {
    WebAuthnClient,
    creationOptions,
    decodeBase64Url,
    encodeBase64Url,
    requestOptions,
} = await import('../../public/assets/js/webauthn-client.js');

test('base64url codec preserves WebAuthn binary values', () => {
    const value = Uint8Array.from([0, 1, 2, 253, 254, 255]).buffer;
    const encoded = encodeBase64Url(value);
    assert.equal(encoded, 'AAEC_f7_');
    assert.deepEqual([...new Uint8Array(decodeBase64Url(encoded))], [0, 1, 2, 253, 254, 255]);
});

test('creation and assertion options convert challenge and credential identifiers to buffers', () => {
    const creation = creationOptions({
        challenge: 'AQID',
        user: { id: 'BAUG', name: 'account', displayName: 'Account' },
        excludeCredentials: [{ type: 'public-key', id: 'BwgJ' }],
    });
    const assertion = requestOptions({
        challenge: 'AQID',
        allowCredentials: [{ type: 'public-key', id: 'BwgJ' }],
    });

    assert.deepEqual([...new Uint8Array(creation.challenge)], [1, 2, 3]);
    assert.deepEqual([...new Uint8Array(creation.user.id)], [4, 5, 6]);
    assert.deepEqual([...new Uint8Array(creation.excludeCredentials[0].id)], [7, 8, 9]);
    assert.deepEqual([...new Uint8Array(assertion.allowCredentials[0].id)], [7, 8, 9]);
});

test('authentication performs exactly one options and one verification mutation', async () => {
    const calls = [];
    Object.defineProperty(globalThis, 'PublicKeyCredential', { value: function PublicKeyCredential() {}, configurable: true });
    Object.defineProperty(globalThis, 'navigator', {
        value: {
            credentials: {
                get: async () => ({
                    id: 'credential-id',
                    rawId: Uint8Array.from([1, 2]).buffer,
                    type: 'public-key',
                    authenticatorAttachment: 'platform',
                    getClientExtensionResults: () => ({}),
                    response: {
                        clientDataJSON: Uint8Array.from([3]).buffer,
                        authenticatorData: Uint8Array.from([4]).buffer,
                        signature: Uint8Array.from([5]).buffer,
                        userHandle: Uint8Array.from([6]).buffer,
                    },
                }),
            },
        },
        configurable: true,
    });
    const client = new WebAuthnClient(async (path, options) => {
        calls.push({ path, options });
        if (calls.length === 1) {
            return { ceremonyId: 'ceremony-id', publicKey: { challenge: 'AQID', allowCredentials: [] } };
        }
        return { status: 'authenticated', navigate: '/account/security/authentication' };
    });
    const result = await client.authenticate({
        optionsUrl: '/login/passkey/options',
        verifyUrl: '/login/passkey/verify',
        csrfToken: 'csrf-token',
    });

    assert.equal(calls.length, 2);
    assert.equal(calls[0].path, '/login/passkey/options');
    assert.equal(calls[1].path, '/login/passkey/verify');
    assert.equal(calls[1].options.body.credential.rawId, 'AQI');
    assert.equal(result.navigate, '/account/security/authentication');
});
