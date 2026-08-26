<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\RateLimit;

final readonly class IdentityRateLimitPolicy
{
    public function __construct(
        public int $windowSeconds,
        public int $maximumAttempts,
        public int $blockSeconds,
    ) {
        if ($windowSeconds < 1 || $maximumAttempts < 1 || $blockSeconds < 1) {
            throw new \InvalidArgumentException('Identity rate-limit policy is invalid.');
        }
    }
}
