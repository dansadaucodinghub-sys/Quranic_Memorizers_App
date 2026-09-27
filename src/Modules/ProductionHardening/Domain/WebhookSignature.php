<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class WebhookSignature
{
    public function canonicalPayload(string $deliveryId, int $timestamp, string $payload): string
    {
        if ($timestamp <= 0 || $deliveryId === '') {
            throw new \InvalidArgumentException('Delivery identity and timestamp are required.');
        }
        return $deliveryId . '.' . $timestamp . '.' . $payload;
    }

    public function sign(string $keyId, string $secret, string $deliveryId, int $timestamp, string $payload): string
    {
        if ($keyId === '' || strlen($secret) < 32) {
            throw new \InvalidArgumentException('A versioned signing key and at least 256 bits of secret material are required.');
        }
        return 'v1=' . hash_hmac('sha256', $this->canonicalPayload($deliveryId, $timestamp, $payload), $secret);
    }

    public function verify(string $signature, string $secret, string $deliveryId, int $timestamp, string $payload, int $now, int $tolerance = 300): bool
    {
        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }
        return hash_equals($this->sign('verification', $secret, $deliveryId, $timestamp, $payload), $signature);
    }
}
