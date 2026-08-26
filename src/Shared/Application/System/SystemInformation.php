<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\System;

final readonly class SystemInformation
{
    public function __construct(
        private string $applicationName,
        private string $applicationCode,
        private string $developmentVersion,
        private string $frozenBaseline,
        private string $currentPhase,
        private string $currentBatch,
        private string $environment,
        private bool $debugEnabled,
        private string $timezone,
        private string $configurationSource,
        private string $phpVersion,
        private bool $runtimeRequirementsSatisfied,
    ) {
    }

    public function applicationName(): string
    {
        return $this->applicationName;
    }
    public function applicationCode(): string
    {
        return $this->applicationCode;
    }
    public function developmentVersion(): string
    {
        return $this->developmentVersion;
    }
    public function frozenBaseline(): string
    {
        return $this->frozenBaseline;
    }
    public function currentPhase(): string
    {
        return $this->currentPhase;
    }
    public function currentBatch(): string
    {
        return $this->currentBatch;
    }
    public function environment(): string
    {
        return $this->environment;
    }
    public function debugEnabled(): bool
    {
        return $this->debugEnabled;
    }
    public function timezone(): string
    {
        return $this->timezone;
    }
    public function configurationSource(): string
    {
        return $this->configurationSource;
    }
    public function phpVersion(): string
    {
        return $this->phpVersion;
    }
    public function runtimeRequirementsSatisfied(): bool
    {
        return $this->runtimeRequirementsSatisfied;
    }
}
