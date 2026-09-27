<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class BoundedRetryPolicy
{
    public function __construct(private int $maximumAttempts = 6, private int $baseSeconds = 30, private int $maximumSeconds = 3600)
    {
        if ($maximumAttempts < 1 || $baseSeconds < 1 || $maximumSeconds < $baseSeconds) {
            throw new \InvalidArgumentException('Retry policy bounds are invalid.');
        }
    }

    public function shouldRetry(int $attempt, ?int $responseStatus): bool
    {
        if ($attempt >= $this->maximumAttempts) {
            return false;
        }
        return $responseStatus === null || $responseStatus === 408 || $responseStatus === 429 || $responseStatus >= 500;
    }

    public function delaySeconds(int $attempt, string $stableKey): int
    {
        $exponential = min($this->maximumSeconds, $this->baseSeconds * (2 ** max(0, $attempt - 1)));
        $jitter = hexdec(substr(hash('sha256', $stableKey . ':' . $attempt), 0, 4)) % max(1, intdiv($exponential, 4));
        return min($this->maximumSeconds, $exponential + $jitter);
    }
}
