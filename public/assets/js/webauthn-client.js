import { submitJsonMutation } from './mutation-fetch-client.js';

function decodeBase64Url(value) {
    const padded = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
    const bytes = Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
    return bytes.buffer;
}

function encodeBase64Url(value) {
    const bytes = new Uint8Array(value);
    let binary = '';
    for (const byte of bytes) binary += String.fromCharCode(byte);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function creationOptions(publicKey) {
    return {
        ...publicKey,
        challenge: decodeBase64Url(publicKey.challenge),
        user: { ...publicKey.user, id: decodeBase64Url(publicKey.user.id) },
        excludeCredentials: (publicKey.excludeCredentials || []).map((item) => ({
            ...item,
            id: decodeBase64Url(item.id),
        })),
    };
}

function requestOptions(publicKey) {
    return {
        ...publicKey,
        challenge: decodeBase64Url(publicKey.challenge),
        allowCredentials: (publicKey.allowCredentials || []).map((item) => ({
            ...item,
            id: decodeBase64Url(item.id),
        })),
    };
}

function credentialPayload(credential) {
    const response = credential.response;
    const payload = {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: credential.type,
        authenticatorAttachment: credential.authenticatorAttachment,
        clientExtensionResults: credential.getClientExtensionResults(),
        response: {
            clientDataJSON: encodeBase64Url(response.clientDataJSON),
        },
    };
    if ('attestationObject' in response) {
        payload.response.attestationObject = encodeBase64Url(response.attestationObject);
        payload.response.transports = typeof response.getTransports === 'function' ? response.getTransports() : [];
    } else {
        payload.response.authenticatorData = encodeBase64Url(response.authenticatorData);
        payload.response.signature = encodeBase64Url(response.signature);
        payload.response.userHandle = response.userHandle === null ? null : encodeBase64Url(response.userHandle);
    }
    return payload;
}

export class WebAuthnClient {
    constructor(mutationImplementation = submitJsonMutation) {
        this.submit = mutationImplementation;
    }

    supported() {
        return Boolean(typeof globalThis.PublicKeyCredential === 'function' && navigator.credentials);
    }

    async register({ optionsUrl, verifyUrl, csrfToken, displayName }) {
        this.#requireSupport();
        const options = await this.#post(optionsUrl, csrfToken, {});
        const credential = await navigator.credentials.create({ publicKey: creationOptions(options.publicKey) });
        if (!credential) throw new Error('PASSKEY_CEREMONY_CANCELLED');
        return this.#post(verifyUrl, csrfToken, {
            ceremonyId: options.ceremonyId,
            displayName,
            credential: credentialPayload(credential),
        });
    }

    async authenticate({ optionsUrl, verifyUrl, csrfToken }) {
        this.#requireSupport();
        const options = await this.#post(optionsUrl, csrfToken, {});
        const credential = await navigator.credentials.get({ publicKey: requestOptions(options.publicKey) });
        if (!credential) throw new Error('PASSKEY_CEREMONY_CANCELLED');
        return this.#post(verifyUrl, csrfToken, {
            ceremonyId: options.ceremonyId,
            credential: credentialPayload(credential),
        });
    }

    #requireSupport() {
        if (!this.supported()) throw new Error('PASSKEY_NOT_SUPPORTED');
    }

    async #post(path, csrfToken, body) {
        return this.submit(path, { csrfToken, body });
    }
}

export { creationOptions, credentialPayload, decodeBase64Url, encodeBase64Url, requestOptions };
