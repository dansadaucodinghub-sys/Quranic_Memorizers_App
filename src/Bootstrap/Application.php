<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;

final readonly class Application
{
    public function __construct(
        private ApplicationMetadata $metadata,
        private RuntimeRequirements $runtimeRequirements,
        private ApplicationConfiguration $configuration,
    ) {
    }

    public function metadata(): ApplicationMetadata
    {
        return $this->metadata;
    }

    public function runtimeRequirements(): RuntimeRequirements
    {
        return $this->runtimeRequirements;
    }

    public function configuration(): ApplicationConfiguration
    {
        return $this->configuration;
    }

    /**
     * @param list<string>|null $loadedExtensions
     */
    public function validateRuntime(
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
    ): RuntimeRequirementResult {
        if ($phpVersion === null && $loadedExtensions === null) {
            return $this->runtimeRequirements->evaluateCurrentRuntime();
        }

        if ($phpVersion === null || $loadedExtensions === null) {
            throw new InvalidArgumentException(
                'A supplied runtime version and extension list must be provided together.',
            );
        }

        return $this->runtimeRequirements->evaluate($phpVersion, $loadedExtensions);
    }
}
