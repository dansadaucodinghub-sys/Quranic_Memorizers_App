<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/** Public metadata only.  Private material never crosses this boundary. */
final readonly class CertificateSigningKey
{
    public function __construct(
        public string $keyCode,
        public string $providerCode,
        public string $providerKeyReference,
        public string $publicKey,
        public string $status,
    ) {
        if ($keyCode === '' || $providerCode === '' || $providerKeyReference === '') {
            throw new \InvalidArgumentException('Certificate signing-key metadata is incomplete.');
        }
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException('Certificate signing-key public key is invalid.');
        }
        if (!in_array($status, ['ACTIVE', 'VERIFY_ONLY', 'RETIRED', 'REVOKED'], true)) {
            throw new \InvalidArgumentException('Certificate signing-key status is invalid.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->publicKey, true);
    }

    public function canIssue(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
