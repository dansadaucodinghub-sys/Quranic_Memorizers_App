<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

final readonly class DeviceRequestSignature
{
    public function canonical(
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $bodyHash,
        string $deviceId,
        string $packageId,
    ): string {
        if (abs(time() - $timestamp) > 300 || preg_match('/\A[a-zA-Z0-9_-]{16,128}\z/', $nonce) !== 1) {
            throw new \DomainException('Device request timestamp or nonce is invalid.');
        }

        return implode("\n", [strtoupper($method), $path, (string) $timestamp, $nonce, $bodyHash, $deviceId, $packageId]);
    }

    public function verify(string $canonical, string $signature, string $publicKey): bool
    {
        return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
            && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
    }
}
