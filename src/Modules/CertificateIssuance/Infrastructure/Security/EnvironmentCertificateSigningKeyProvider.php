<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Security;

use Qmdb\Modules\CertificateIssuance\Application\CertificateSigningKeyProvider;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;

/**
 * Production adapter for a process-secret provider.  Deployments should inject
 * this variable from their secret manager; no private material is persisted or
 * serialised by the application.
 */
final readonly class EnvironmentCertificateSigningKeyProvider implements CertificateSigningKeyProvider
{
    public function __construct(private bool $production)
    {
    }

    public function providerCode(): string
    {
        return 'ENVIRONMENT_SECRET';
    }

    public function sign(CertificateSigningKey $key, string $canonicalManifest): string
    {
        if (!$key->canIssue()) {
            throw new \DomainException('A non-active signing key cannot issue a certificate.');
        }
        if ($key->providerCode !== $this->providerCode()) {
            throw new \DomainException('Signing key belongs to a different provider.');
        }

        $variable = 'QMDB_CERTIFICATE_SIGNING_KEY_' . strtoupper(str_replace('-', '_', $key->providerKeyReference));
        $encoded = getenv($variable);
        if (!is_string($encoded) || $encoded === '') {
            throw new \RuntimeException('Certificate signing key is unavailable from the configured provider.');
        }
        $privateKey = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        try {
            if (strlen($privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                throw new \RuntimeException('Configured certificate signing key has an invalid length.');
            }
            $derivedPublic = sodium_crypto_sign_publickey_from_secretkey($privateKey);
            if (!hash_equals($key->publicKey, $derivedPublic)) {
                throw new \RuntimeException('Configured certificate signing key does not match persisted public metadata.');
            }

            return sodium_crypto_sign_detached($canonicalManifest, $privateKey);
        } finally {
            sodium_memzero($privateKey);
        }
    }

    public function verifiesPublicKey(CertificateSigningKey $key): bool
    {
        if ($key->providerCode !== $this->providerCode()) {
            return false;
        }
        if (!$this->production) {
            return true;
        }

        $variable = 'QMDB_CERTIFICATE_SIGNING_KEY_' . strtoupper(str_replace('-', '_', $key->providerKeyReference));
        $encoded = getenv($variable);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }
        $privateKey = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        try {
            return strlen($privateKey) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
                && hash_equals($key->publicKey, sodium_crypto_sign_publickey_from_secretkey($privateKey));
        } finally {
            sodium_memzero($privateKey);
        }
    }
}
