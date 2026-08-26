<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\System;

use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\RuntimeEnvironment;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Application\Query\QueryHandler;
use Qmdb\Shared\Configuration\ApplicationConfiguration;

final readonly class GetSystemInformationHandler implements QueryHandler
{
    public function __construct(
        private ApplicationMetadata $metadata,
        private ApplicationConfiguration $configuration,
        private RuntimeRequirements $runtimeRequirements,
        private RuntimeEnvironment $runtimeEnvironment,
    ) {
    }

    public function __invoke(GetSystemInformation $query): SystemInformation
    {
        $runtimeResult = $this->runtimeRequirements->evaluate(
            $this->runtimeEnvironment->phpVersion(),
            $this->runtimeEnvironment->loadedExtensions(),
        );

        return new SystemInformation(
            applicationName: $this->metadata->applicationName(),
            applicationCode: $this->metadata->applicationCode(),
            developmentVersion: $this->metadata->developmentVersion(),
            frozenBaseline: $this->metadata->frozenBaseline(),
            currentPhase: $this->metadata->currentPhase(),
            currentBatch: $this->metadata->currentBatch(),
            environment: $this->configuration->environment()->toSafeString(),
            debugEnabled: $this->configuration->debugEnabled(),
            timezone: $this->configuration->timezone()->getName(),
            configurationSource: $this->configuration->source()->toSafeDisplay(),
            phpVersion: $this->runtimeEnvironment->phpVersion(),
            runtimeRequirementsSatisfied: $runtimeResult->isSatisfied(),
        );
    }
}
