<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

use InvalidArgumentException;

final readonly class Application
{
    public function __construct(
        private ApplicationMetadata $metadata,
        private RuntimeRequirements $runtimeRequirements,
    ) {
    }

    public static function bootstrap(): self
    {
        return new self(
            metadata: ApplicationMetadata::current(),
            runtimeRequirements: new RuntimeRequirements(),
        );
    }

    public function metadata(): ApplicationMetadata
    {
        return $this->metadata;
    }

    public function runtimeRequirements(): RuntimeRequirements
    {
        return $this->runtimeRequirements;
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
