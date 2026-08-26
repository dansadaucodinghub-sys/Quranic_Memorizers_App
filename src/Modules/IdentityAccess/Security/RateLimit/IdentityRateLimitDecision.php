<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\RateLimit;

final readonly class IdentityRateLimitDecision
{
    private function __construct(public bool $allowed, public int $retryAfterSeconds)
    {
    }

    public static function allowed(): self
    {
        return new self(true, 0);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(false, max(1, $retryAfterSeconds));
    }
}
