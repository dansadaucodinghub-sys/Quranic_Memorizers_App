<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Configuration;

final readonly class SecurityAuditConfiguration
{
    public function __construct(
        public bool $productionLike,
        public int $metadataMaximumBytes,
        public int $verificationBatchSize,
        public int $checkpointIntervalSeconds,
        public int $checkpointMaximumStreams,
        public int $integrityKeyVersion,
    ) {
    }
}
