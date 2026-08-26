<?php

declare(strict_types=1);

namespace Qmdb\Shared\Module;

use Qmdb\Shared\Application\Command\CommandHandlerMap;
use Qmdb\Shared\Application\Event\DomainEventSubscriberMap;
use Qmdb\Shared\Application\Query\QueryHandlerMap;

final readonly class ModuleCompilation
{
    /** @param array<string, list<string>> $moduleDependencies */
    public function __construct(
        public CommandHandlerMap $commandHandlers,
        public QueryHandlerMap $queryHandlers,
        public DomainEventSubscriberMap $eventSubscribers,
        public array $moduleDependencies,
    ) {
    }
}
