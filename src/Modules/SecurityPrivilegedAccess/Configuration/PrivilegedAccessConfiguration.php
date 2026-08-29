<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Configuration;

final readonly class PrivilegedAccessConfiguration
{
    public function __construct(
        public int $requestTtlSeconds,
        public int $temporaryPrivilegeMaximumTtlSeconds,
        public int $supportAccessMaximumTtlSeconds,
        public int $breakGlassMaximumTtlSeconds,
        public int $maximumPermissions,
        public int $reviewTtlSeconds,
        public int $justificationMaximumBytes,
        public int $referenceMaximumBytes,
        public int $maintenanceBatchSize,
        public int $requestWindowSeconds,
        public int $requestMaximumAttempts,
        public int $breakGlassWindowSeconds,
        public int $breakGlassMaximumAttempts,
    ) {
    }
}
