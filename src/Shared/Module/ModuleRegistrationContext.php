<?php

declare(strict_types=1);

namespace Qmdb\Shared\Module;

use Qmdb\Shared\Application\Command\CommandHandlerRegistry;
use Qmdb\Shared\Application\Command\CommandRegistration;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistration;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistry;
use Qmdb\Shared\Application\Query\QueryHandlerRegistry;
use Qmdb\Shared\Application\Query\QueryRegistration;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistrationRegistry;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\DependencyInjection\ServiceAlias;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;

final class ModuleRegistrationContext
{
    private bool $frozen = false;

    public function __construct(
        private readonly ModuleId $moduleId,
        private readonly ContainerBuilder $containerBuilder,
        private readonly CommandHandlerRegistry $commands,
        private readonly QueryHandlerRegistry $queries,
        private readonly DomainEventSubscriberRegistry $events,
        private readonly ?ScheduledTaskRegistrationRegistry $scheduledTasks = null,
    ) {
    }

    public function scheduledTask(ScheduledTaskRegistration $registration): void
    {
        $this->assertOpen();
        if ($registration->owningModule !== $this->moduleId->value()) {
            throw new ModuleDependencyException('Scheduled task ownership does not match its registering module.');
        }
        if ($this->scheduledTasks === null) {
            throw new ModuleDependencyException('Scheduled task registration is unavailable.');
        }
        $this->scheduledTasks->register($registration);
    }

    public function service(ServiceDefinition $definition): void
    {
        $this->assertOpen();
        $this->assertOwnership($definition->ownerModuleId);
        $this->containerBuilder->register($definition);
    }

    public function alias(string $alias, string $target): void
    {
        $this->assertOpen();
        $this->containerBuilder->registerAlias(new ServiceAlias(
            $alias,
            $target,
            $this->moduleId->value(),
        ));
    }

    public function commandHandler(string $commandClass, string $handlerServiceId): void
    {
        $this->assertOpen();
        $this->commands->register(new CommandRegistration(
            $commandClass,
            $handlerServiceId,
            $this->moduleId->value(),
        ));
    }

    public function queryHandler(string $queryClass, string $handlerServiceId): void
    {
        $this->assertOpen();
        $this->queries->register(new QueryRegistration(
            $queryClass,
            $handlerServiceId,
            $this->moduleId->value(),
        ));
    }

    public function eventSubscriber(string $eventClass, string $handlerServiceId): void
    {
        $this->assertOpen();
        $this->events->register(new DomainEventSubscriberRegistration(
            $eventClass,
            $handlerServiceId,
            $this->moduleId->value(),
        ));
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    private function assertOpen(): void
    {
        if ($this->frozen) {
            throw new ModuleDependencyException(sprintf(
                'Registration for module "%s" is closed.',
                $this->moduleId->value(),
            ));
        }
    }

    private function assertOwnership(string $ownerModuleId): void
    {
        if ($ownerModuleId !== $this->moduleId->value()) {
            throw new ModuleDependencyException(sprintf(
                'Module "%s" cannot register a service owned by "%s".',
                $this->moduleId->value(),
                $ownerModuleId,
            ));
        }
    }
}
