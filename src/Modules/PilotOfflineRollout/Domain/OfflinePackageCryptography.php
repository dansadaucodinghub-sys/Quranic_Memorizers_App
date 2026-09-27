<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

final readonly class OfflinePackageCryptography
{
    private string $signingSecretKey;
    private string $signingPublicKey;
    private string $encryptionKey;
    private bool $productionConfigured;

    public function __construct(string $signingSeed, string $encryptionMaterial, bool $productionConfigured = true)
    {
        if (strlen($signingSeed) < 32 || strlen($encryptionMaterial) < 32) {
            throw new \InvalidArgumentException('P13 cryptographic key material must contain at least 32 bytes.');
        }
        $pair = sodium_crypto_sign_seed_keypair(hash('sha256', $signingSeed, true));
        $this->signingSecretKey = sodium_crypto_sign_secretkey($pair);
        $this->signingPublicKey = sodium_crypto_sign_publickey($pair);
        $this->encryptionKey = hash('sha256', $encryptionMaterial, true);
        $this->productionConfigured = $productionConfigured;
    }

    public function productionConfigured(): bool
    {
        return $this->productionConfigured;
    }

    public function publicKey(): string
    {
        return $this->signingPublicKey;
    }

    public function sign(string $manifest): string
    {
        return sodium_crypto_sign_detached($manifest, $this->signingSecretKey);
    }

    public function verify(string $manifest, string $signature): bool
    {
        return $signature !== ''
            && sodium_crypto_sign_verify_detached($signature, $manifest, $this->signingPublicKey);
    }

    /** @return array{nonce:string,ciphertext:string} */
    public function encrypt(string $plaintext, string $associatedData): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return [
            'nonce' => $nonce,
            'ciphertext' => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                $associatedData,
                $nonce,
                $this->encryptionKey,
            ),
        ];
    }

    public function decrypt(string $ciphertext, string $nonce, string $associatedData): string
    {
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $associatedData,
            $nonce,
            $this->encryptionKey,
        );
        if (!is_string($plaintext)) {
            throw new \DomainException('Offline package authentication failed.');
        }

        return $plaintext;
    }
}
