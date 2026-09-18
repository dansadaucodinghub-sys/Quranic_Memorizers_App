<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\MediaCatalog\Domain\MediaLifecycle;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaCatalog\Infrastructure\Persistence\MySqlMediaEvidenceRepository;
use Qmdb\Modules\MediaCatalog\Interface\Console\CompetitionP9VerifyConsoleCommand;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaCatalogModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('media.catalog');
    }
    public function dependencies(): array
    {
        return [new ModuleId('foundation.database'), new ModuleId('foundation.background'), new ModuleId('tenancy.context'), new ModuleId('people.profiles')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(MediaLifecycle::class, 'media.catalog', new MediaLifecycle()));
        $context->service(ServiceDefinition::factory(MySqlMediaEvidenceRepository::class, 'media.catalog', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlMediaEvidenceRepository => new MySqlMediaEvidenceRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(MediaEvidenceRepository::class, MySqlMediaEvidenceRepository::class);
        $context->service(ServiceDefinition::factory(
            CompetitionP9VerifyConsoleCommand::class,
            'media.catalog',
            [DatabaseConnectionProvider::class, ScheduledTaskMap::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP9VerifyConsoleCommand => new CompetitionP9VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, ScheduledTaskMap::class))),
        ));
    }
}
