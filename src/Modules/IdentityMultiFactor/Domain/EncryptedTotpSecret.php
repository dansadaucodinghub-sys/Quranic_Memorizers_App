<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

final readonly class EncryptedTotpSecret
{
    public function __construct(
        private string $ciphertext,
        private string $nonce,
        public int $keyVersion,
    ) {
        if (
            $ciphertext === '' || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            || $keyVersion < 1
        ) {
            throw new \InvalidArgumentException('Encrypted TOTP secret is invalid.');
        }
    }

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    /** @return array{secret: string} */
    public function __debugInfo(): array
    {
        return ['secret' => '[REDACTED]'];
    }
}
