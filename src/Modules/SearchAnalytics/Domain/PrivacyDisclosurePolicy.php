<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Domain;

final class PrivacyDisclosurePolicy
{
    /** @return array{value:?int,suppression_code:?string} */
    public function disclose(
        int $rawCount,
        string $policyType,
        int $minimumCellSize,
        int $roundingBucket,
        bool $platformAuthorized,
    ): array {
        if ($rawCount < 0 || $minimumCellSize < 0 || $roundingBucket < 0) {
            throw new \InvalidArgumentException('Disclosure inputs cannot be negative.');
        }
        if ($policyType === 'PLATFORM_PRIVATE_ONLY' && !$platformAuthorized) {
            return ['value' => null, 'suppression_code' => 'PRIVATE_SCOPE'];
        }
        if ($policyType === 'MINIMUM_CELL_SIZE' && $rawCount < $minimumCellSize) {
            return ['value' => null, 'suppression_code' => 'SMALL_GROUP'];
        }
        if ($policyType === 'ROUND_TO_BUCKET') {
            if ($roundingBucket < 1) {
                throw new \InvalidArgumentException('A rounding policy requires a positive bucket.');
            }
            return ['value' => (int) (round($rawCount / $roundingBucket) * $roundingBucket), 'suppression_code' => null];
        }
        if (!in_array($policyType, ['NONE', 'MINIMUM_CELL_SIZE', 'PLATFORM_PRIVATE_ONLY'], true)) {
            throw new \InvalidArgumentException('Unknown disclosure policy.');
        }

        return ['value' => $rawCount, 'suppression_code' => null];
    }
}
