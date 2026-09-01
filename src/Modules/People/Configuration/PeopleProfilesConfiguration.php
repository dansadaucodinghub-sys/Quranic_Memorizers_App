<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Configuration;

final readonly class PeopleProfilesConfiguration
{
    public function __construct(
        public int $minorThresholdYears,
        public int $maximumAgeYears,
        public int $nameMaximumBytes,
        public int $searchNameMaximumBytes,
        public int $maximumDependentsPerGuardian,
        public int $mutationWindowSeconds,
        public int $mutationMaximumAttempts,
        public int $dependentCreationWindowSeconds,
        public int $dependentCreationMaximumAttempts,
    ) {
    }
}
