<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Security;

use Qmdb\Modules\CertificateIssuance\Application\CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;

final class Ed25519CertificateSignatureVerifier implements CertificateSignatureVerifier
{
    public function verify(CertificateSigningKey $key, string $canonicalManifest, string $signature): bool
    {
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || $canonicalManifest === '' || strlen($key->publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $canonicalManifest, $key->publicKey);
    }
}
