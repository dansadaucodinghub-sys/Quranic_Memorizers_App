<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Configuration;

final readonly class OrganizationAffiliationsConfiguration
{
    public function __construct(
        public int $requestTtlSeconds,
        public int $codeEntropyBits,
        public int $titleMaximumBytes,
        public int $maximumRoles,
        public int $maximumUnits,
        public int $maximumPageSize,
        public int $maintenanceBatchSize,
        public int $requestWindowSeconds,
        public int $requestMaximumAttempts,
        public int $responseWindowSeconds,
        public int $responseMaximumAttempts,
        public int $mutationWindowSeconds,
        public int $mutationMaximumAttempts,
    ) {
    }
}
