<?php

declare(strict_types=1);

namespace Qmdb\Shared\Module;

use Qmdb\Shared\Application\Command\CommandHandlerRegistry;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistry;
use Qmdb\Shared\Application\Handler\HandlerResolver;
use Qmdb\Shared\Application\Handler\ServiceHandlerResolver;
use Qmdb\Shared\Application\Query\QueryHandlerRegistry;
use Qmdb\Shared\Application\Command\CommandHandlerMap;
use Qmdb\Shared\Application\Event\DomainEventSubscriberMap;
use Qmdb\Shared\Application\Query\QueryHandlerMap;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\ContainerBuilder;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Background\Scheduler\DeferredScheduledTaskHandler;
use Qmdb\Shared\Background\Scheduler\ScheduledTask;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistrationRegistry;

final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modulesById = [];

    /** @var list<Module> */
    private array $orderedModules;

    private bool $compiled = false;

    /** @param list<Module> $modules */
    public function __construct(array $modules)
    {
        if ($modules === []) {
            throw new ModuleDependencyException('At least one module must be registered.');
        }

        $classIds = [];
        foreach ($modules as $module) {
            $id = $module->id()->value();
            if (isset($this->modulesById[$id])) {
                throw new ModuleDependencyException(sprintf('Duplicate module id: "%s".', $id));
            }

            $class = $module::class;
            if (isset($classIds[$class]) && $classIds[$class] !== $id) {
                throw new ModuleDependencyException(sprintf(
                    'Module class "%s" supplied conflicting identifiers.',
                    $class,
                ));
            }
            $classIds[$class] = $id;
            $this->modulesById[$id] = $module;
        }

        ksort($this->modulesById);
        $this->validateDependencies();
        $this->orderedModules = $this->resolveOrder();
    }

    /** @return list<string> */
    public function orderedModuleIds(): array
    {
        return array_map(
            static fn (Module $module): string => $module->id()->value(),
            $this->orderedModules,
        );
    }

    public function compile(ContainerBuilder $builder): ModuleCompilation
    {
        if ($this->compiled) {
            throw new ModuleDependencyException('Module registry is already compiled.');
        }

        $commands = new CommandHandlerRegistry();
        $queries = new QueryHandlerRegistry();
        $events = new DomainEventSubscriberRegistry();
        $scheduledTasks = new ScheduledTaskRegistrationRegistry();

        foreach ($this->orderedModules as $module) {
            $context = new ModuleRegistrationContext(
                $module->id(),
                $builder,
                $commands,
                $queries,
                $events,
                $scheduledTasks,
            );
            $module->register($context);
            $context->freeze();
        }

        $commandMap = $commands->freeze();
        $queryMap = $queries->freeze();
        $eventMap = $events->freeze();
        $scheduledTaskRegistrations = $scheduledTasks->freeze();
        $handlerServiceIds = array_values(array_unique(array_merge(
            $commandMap->handlerServiceIds(),
            $queryMap->handlerServiceIds(),
            $eventMap->handlerServiceIds(),
        )));
        foreach ($handlerServiceIds as $handlerServiceId) {
            if (!$builder->has($handlerServiceId)) {
                throw new ModuleDependencyException(sprintf(
                    'Handler service "%s" is not registered.',
                    $handlerServiceId,
                ));
            }
        }

        $moduleDependencies = $this->moduleDependencies();
        $applicationModuleId = isset($moduleDependencies['foundation.application'])
            ? 'foundation.application'
            : $this->orderedModules[0]->id()->value();
        $builder->register(ServiceDefinition::instance(
            CommandHandlerMap::class,
            $applicationModuleId,
            $commandMap,
        ));
        $builder->register(ServiceDefinition::instance(
            QueryHandlerMap::class,
            $applicationModuleId,
            $queryMap,
        ));
        $builder->register(ServiceDefinition::instance(
            DomainEventSubscriberMap::class,
            $applicationModuleId,
            $eventMap,
        ));
        $builder->register(ServiceDefinition::factory(
            HandlerResolver::class,
            $applicationModuleId,
            $handlerServiceIds,
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ServiceHandlerResolver => new ServiceHandlerResolver(
                    $handlerServiceIds,
                    static fn (string $serviceId): object => $resolver->get($serviceId),
                ),
            ),
        ));
        if (isset($moduleDependencies['foundation.background'])) {
            $scheduledHandlerServiceIds = array_values(array_unique(array_map(
                static fn (ScheduledTaskRegistration $registration): string => $registration->handlerServiceId,
                $scheduledTaskRegistrations,
            )));
            $builder->register(ServiceDefinition::extensionFactory(
                ScheduledTaskMap::class,
                'foundation.background',
                [],
                $scheduledHandlerServiceIds,
                new ClosureServiceFactory(static function (DependencyResolver $resolver) use (
                    $scheduledTaskRegistrations,
                ): ScheduledTaskMap {
                    $tasks = [];
                    foreach ($scheduledTaskRegistrations as $registration) {
                        $tasks[] = new ScheduledTask(
                            $registration->id,
                            $registration->description,
                            $registration->schedule,
                            new DeferredScheduledTaskHandler(
                                static fn (): object => $resolver->get($registration->handlerServiceId),
                                $registration->handlerServiceId,
                            ),
                            $registration->leaseSeconds,
                            $registration->owningModule,
                        );
                    }

                    return new ScheduledTaskMap($tasks);
                }),
            ));
        }

        $this->compiled = true;

        return new ModuleCompilation(
            $commandMap,
            $queryMap,
            $eventMap,
            $moduleDependencies,
        );
    }

    /** @return array<string, list<string>> */
    public function moduleDependencies(): array
    {
        $result = [];
        foreach ($this->modulesById as $id => $module) {
            $result[$id] = array_map(
                static fn (ModuleId $dependency): string => $dependency->value(),
                $module->dependencies(),
            );
            sort($result[$id]);
        }

        return $result;
    }

    private function validateDependencies(): void
    {
        foreach ($this->modulesById as $id => $module) {
            $seen = [];
            foreach ($module->dependencies() as $dependency) {
                $dependencyId = $dependency->value();
                if ($dependencyId === $id) {
                    throw new ModuleDependencyException(sprintf('Module "%s" cannot depend on itself.', $id));
                }
                if (isset($seen[$dependencyId])) {
                    throw new ModuleDependencyException(sprintf(
                        'Module "%s" declares dependency "%s" more than once.',
                        $id,
                        $dependencyId,
                    ));
                }
                if (!isset($this->modulesById[$dependencyId])) {
                    throw new ModuleDependencyException(sprintf(
                        'Module "%s" depends on unknown module "%s".',
                        $id,
                        $dependencyId,
                    ));
                }
                $seen[$dependencyId] = true;
            }
        }
    }

    /** @return list<Module> */
    private function resolveOrder(): array
    {
        $ordered = [];
        $temporary = [];
        $permanent = [];
        foreach (array_keys($this->modulesById) as $id) {
            $this->visit($id, $temporary, $permanent, $ordered);
        }

        return $ordered;
    }

    /**
     * @param array<string, true> $temporary
     * @param array<string, true> $permanent
     * @param list<Module> $ordered
     */
    private function visit(string $id, array &$temporary, array &$permanent, array &$ordered): void
    {
        if (isset($permanent[$id])) {
            return;
        }
        if (isset($temporary[$id])) {
            throw new ModuleDependencyException(sprintf('Circular module dependency detected at "%s".', $id));
        }

        $temporary[$id] = true;
        $dependencies = $this->modulesById[$id]->dependencies();
        usort(
            $dependencies,
            static fn (ModuleId $left, ModuleId $right): int => $left->value() <=> $right->value(),
        );
        foreach ($dependencies as $dependency) {
            $this->visit($dependency->value(), $temporary, $permanent, $ordered);
        }
        unset($temporary[$id]);
        $permanent[$id] = true;
        $ordered[] = $this->modulesById[$id];
    }
}
