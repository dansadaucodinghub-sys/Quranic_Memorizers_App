<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

use InvalidArgumentException;
use Qmdb\Shared\Application\Command\CommandBus;
use Qmdb\Shared\Application\Event\DomainEventDispatcher;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Configuration\ApplicationConfiguration;

final readonly class Application
{
    public function __construct(
        private ApplicationMetadata $metadata,
        private RuntimeRequirements $runtimeRequirements,
        private ApplicationConfiguration $configuration,
        private QueryBus $queryBus,
        private CommandBus $commandBus,
        private DomainEventDispatcher $eventDispatcher,
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

    public function queryBus(): QueryBus
    {
        return $this->queryBus;
    }

    public function commandBus(): CommandBus
    {
        return $this->commandBus;
    }

    public function eventDispatcher(): DomainEventDispatcher
    {
        return $this->eventDispatcher;
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
