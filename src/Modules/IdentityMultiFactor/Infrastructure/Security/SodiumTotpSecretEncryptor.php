<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security;

use Qmdb\Modules\IdentityMultiFactor\Domain\EncryptedTotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecretEncryptor;
use SensitiveParameter;

final readonly class SodiumTotpSecretEncryptor implements TotpSecretEncryptor
{
    public function __construct(
        #[SensitiveParameter] private string $key,
        private int $keyVersion,
    ) {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES || $keyVersion < 1) {
            throw new \InvalidArgumentException('MFA encryption key configuration is invalid.');
        }
    }

    public function encrypt(
        TotpSecret $secret,
        string $accountPublicId,
        string $authenticatorPublicId,
    ): EncryptedTotpSecret {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $secret->revealForEncryption(),
            $this->aad($accountPublicId, $authenticatorPublicId),
            $nonce,
            $this->key,
        );

        return new EncryptedTotpSecret($ciphertext, $nonce, $this->keyVersion);
    }

    public function decrypt(
        EncryptedTotpSecret $secret,
        string $accountPublicId,
        string $authenticatorPublicId,
    ): TotpSecret {
        if ($secret->keyVersion !== $this->keyVersion) {
            throw new \RuntimeException('TOTP secret key version is unavailable.');
        }
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $secret->ciphertext(),
            $this->aad($accountPublicId, $authenticatorPublicId),
            $secret->nonce(),
            $this->key,
        );
        if (!is_string($plaintext)) {
            throw new \RuntimeException('TOTP secret authentication failed.');
        }

        return new TotpSecret($plaintext);
    }

    private function aad(string $accountPublicId, string $authenticatorPublicId): string
    {
        return "qmdb:totp-secret:v1\0" . $accountPublicId . "\0" . $authenticatorPublicId;
    }
}
