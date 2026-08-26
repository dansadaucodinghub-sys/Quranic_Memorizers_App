<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\IdentityAccess;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;

final class RecordingIdentityRateLimiter implements IdentityRateLimiter
{
    public int $consumeCalls = 0;
    public int $resetCalls = 0;

    public function __construct(private readonly IdentityRateLimitDecision $decision)
    {
    }

    public function consume(array $attempts, DateTimeImmutable $now): IdentityRateLimitDecision
    {
        $this->consumeCalls++;
        return $this->decision;
    }

    public function reset(array $attempts): void
    {
        $this->resetCalls++;
    }
}
