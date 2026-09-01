<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Throwable;

final readonly class PeopleProfilesReadinessCheck
{
    public function __construct(
        private PeopleProfilesConfiguration $configuration,
        private SchemaHealthCheck $schema,
        private PeopleProfilesVerificationProbe $verification,
    ) {
    }

    public function isReady(): bool
    {
        try {
            return $this->configuration->minorThresholdYears === 18
                && $this->configuration->maximumAgeYears >= 100
                && $this->configuration->nameMaximumBytes <= 200
                && $this->schema->check()->isReady()
                && $this->verification->report()['invalid_rows'] === 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{person_count:int,self_linked_person_count:int,dependent_person_count:int,active_role_profile_count:int,active_guardianship_count:int,invalid_rows:int} */
    public function verificationReport(): array
    {
        return $this->verification->report();
    }
}
