<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\RuntimeEnvironment;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Application\Command\CommandBus;
use Qmdb\Shared\Application\Command\CommandHandlerMap;
use Qmdb\Shared\Application\Command\SynchronousCommandBus;
use Qmdb\Shared\Application\Event\DomainEventDispatcher;
use Qmdb\Shared\Application\Event\DomainEventSubscriberMap;
use Qmdb\Shared\Application\Event\SynchronousDomainEventDispatcher;
use Qmdb\Shared\Application\Handler\HandlerResolver;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Application\Query\QueryHandlerMap;
use Qmdb\Shared\Application\Query\SynchronousQueryBus;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\GetSystemInformationHandler;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class ApplicationServicesFoundationModule implements Module
{
    private const ID = 'foundation.application';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.core')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            GetSystemInformationHandler::class,
            self::ID,
            [
                ApplicationMetadata::class,
                ApplicationConfiguration::class,
                RuntimeRequirements::class,
                RuntimeEnvironment::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): GetSystemInformationHandler =>
                    new GetSystemInformationHandler(
                        ServiceReference::get($resolver, ApplicationMetadata::class),
                        ServiceReference::get($resolver, ApplicationConfiguration::class),
                        ServiceReference::get($resolver, RuntimeRequirements::class),
                        ServiceReference::get($resolver, RuntimeEnvironment::class),
                    ),
            ),
        ));
        $context->queryHandler(GetSystemInformation::class, GetSystemInformationHandler::class);

        $context->service(ServiceDefinition::factory(
            CommandBus::class,
            self::ID,
            [CommandHandlerMap::class, HandlerResolver::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SynchronousCommandBus => new SynchronousCommandBus(
                    ServiceReference::get($resolver, CommandHandlerMap::class),
                    ServiceReference::get($resolver, HandlerResolver::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            QueryBus::class,
            self::ID,
            [QueryHandlerMap::class, HandlerResolver::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SynchronousQueryBus => new SynchronousQueryBus(
                    ServiceReference::get($resolver, QueryHandlerMap::class),
                    ServiceReference::get($resolver, HandlerResolver::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            DomainEventDispatcher::class,
            self::ID,
            [DomainEventSubscriberMap::class, HandlerResolver::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SynchronousDomainEventDispatcher =>
                    new SynchronousDomainEventDispatcher(
                        ServiceReference::get($resolver, DomainEventSubscriberMap::class),
                        ServiceReference::get($resolver, HandlerResolver::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            Application::class,
            self::ID,
            [
                ApplicationMetadata::class,
                RuntimeRequirements::class,
                ApplicationConfiguration::class,
                QueryBus::class,
                CommandBus::class,
                DomainEventDispatcher::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): Application => new Application(
                    ServiceReference::get($resolver, ApplicationMetadata::class),
                    ServiceReference::get($resolver, RuntimeRequirements::class),
                    ServiceReference::get($resolver, ApplicationConfiguration::class),
                    ServiceReference::get($resolver, QueryBus::class),
                    ServiceReference::get($resolver, CommandBus::class),
                    ServiceReference::get($resolver, DomainEventDispatcher::class),
                ),
            ),
        ));
    }
}
