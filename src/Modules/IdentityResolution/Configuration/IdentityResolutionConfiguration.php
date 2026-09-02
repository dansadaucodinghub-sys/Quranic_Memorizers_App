<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Configuration;

final readonly class IdentityResolutionConfiguration
{
    public function __construct(
        public string $pairingHmacKey,
        public int $pairingHmacKeyVersion,
        public int $pairingEntropyBits,
        public int $pairingTtlSeconds,
        public int $pairingMaximumAttempts,
        public int $claimTtlSeconds,
        public int $maximumPageSize,
        public int $maximumAffectedAffiliations,
        public int $maximumConsentAuthorities,
        public int $reviewJustificationMaximumBytes,
        public int $reviewReferenceMaximumBytes,
        public int $maintenanceBatchSize,
        public int $claimWindowSeconds,
        public int $claimMaximumAttempts,
        public int $duplicateWindowSeconds,
        public int $duplicateMaximumAttempts,
    ) {
    }
}
