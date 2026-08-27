<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Configuration;

use InvalidArgumentException;

final readonly class IdentityRecoveryConfiguration
{
    public function __construct(
        public int $ttlSeconds,
        public int $maximumAttempts,
        public int $requestWindowSeconds,
        public int $requestMaximumAttempts,
        public int $confirmWindowSeconds,
        public int $confirmMaximumAttempts,
    ) {
        if ($ttlSeconds < 60 || $ttlSeconds > 86400) {
            throw new InvalidArgumentException('Password recovery TTL is outside the supported range.');
        }
        foreach (
            [
                $maximumAttempts,
                $requestWindowSeconds,
                $requestMaximumAttempts,
                $confirmWindowSeconds,
                $confirmMaximumAttempts,
            ] as $value
        ) {
            if ($value < 1) {
                throw new InvalidArgumentException('Password recovery limits must be positive.');
            }
        }
    }
}
