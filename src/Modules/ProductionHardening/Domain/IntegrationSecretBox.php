<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IntegrationSecretBox
{
    private ?string $key;

    public function __construct(EnvironmentVariables $environment)
    {
        $encoded = $environment->optionalString('P12_INTEGRATION_ENCRYPTION_KEY');
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
        $this->key = is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $decoded : null;
    }

    public function configured(): bool
    {
        return $this->key !== null;
    }

    public function encrypt(string $secret): string
    {
        if ($this->key === null) {
            throw new \DomainException('P12 integration encryption is not configured.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($secret, $nonce, $this->key);
    }

    public function decrypt(string $ciphertext): string
    {
        if ($this->key === null || strlen($ciphertext) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \DomainException('P12 integration secret cannot be decrypted.');
        }
        $nonce = substr($ciphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($ciphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if (!is_string($plain)) {
            throw new \DomainException('P12 integration secret authentication failed.');
        }
        return $plain;
    }
}
