<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Infrastructure\Security;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

final readonly class SodiumContactCipher implements ContactCipher
{
    public function __construct(
        #[SensitiveParameter] private string $key,
        private string $identifier,
    ) {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('Contact encryption key must be exactly 32 bytes.');
        }
        if (preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $identifier) !== 1) {
            throw new InvalidArgumentException('Contact encryption key identifier is invalid.');
        }
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key);
    }

    public function decrypt(string $ciphertext): string
    {
        if (strlen($ciphertext) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Encrypted contact value is invalid.');
        }
        $nonce = substr($ciphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(
            substr($ciphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->key,
        );
        if (!is_string($plaintext)) {
            throw new RuntimeException('Encrypted contact value could not be authenticated.');
        }

        return $plaintext;
    }

    public function keyId(): string
    {
        return $this->identifier;
    }

    public function __debugInfo(): array
    {
        return ['key' => '[REDACTED]', 'identifier' => $this->identifier];
    }
}
