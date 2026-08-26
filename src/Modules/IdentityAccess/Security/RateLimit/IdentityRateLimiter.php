<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\RateLimit;

use DateTimeImmutable;

interface IdentityRateLimiter
{
    /** @param non-empty-list<IdentityRateLimitAttempt> $attempts */
    public function consume(array $attempts, DateTimeImmutable $now): IdentityRateLimitDecision;

    /** @param list<IdentityRateLimitAttempt> $attempts */
    public function reset(array $attempts): void;
}
