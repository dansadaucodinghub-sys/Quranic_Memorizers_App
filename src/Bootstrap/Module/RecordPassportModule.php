<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\RecordPassport\Application\RecordPassportProjectionPolicy;
use Qmdb\Modules\RecordPassport\Infrastructure\Persistence\RecordPassportProjector;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class RecordPassportModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('record.passport');
    }
    public function dependencies(): array
    {
        return [new ModuleId('certificate.issuance'), new ModuleId('people.profiles'), new ModuleId('competition.result_publication'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.web'), new ModuleId('tenancy.context')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(RecordPassportProjectionPolicy::class, 'record.passport', new RecordPassportProjectionPolicy()));
        $context->service(ServiceDefinition::factory(RecordPassportProjector::class, 'record.passport', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): RecordPassportProjector => new RecordPassportProjector(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
    }
}
