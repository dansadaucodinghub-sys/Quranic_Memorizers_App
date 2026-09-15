<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\TrustedArchive\Application\LegacyRecordPayloadValidator;
use Qmdb\Modules\TrustedArchive\Domain\TrustedArchiveHasher;
use Qmdb\Modules\TrustedArchive\Infrastructure\Persistence\TrustedArchiveSealer;
use Qmdb\Modules\TrustedArchive\Application\P8MaintenanceTask;
use Qmdb\Modules\RecordPassport\Infrastructure\Persistence\RecordPassportProjector;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class TrustedArchiveModule implements Module
{
    public function id(): ModuleId { return new ModuleId('trusted.archive'); }
    public function dependencies(): array { return [new ModuleId('certificate.issuance'), new ModuleId('record.passport'), new ModuleId('competition.result_publication'), new ModuleId('foundation.database'), new ModuleId('security.audit'), new ModuleId('security.authorization'), new ModuleId('tenancy.context')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(TrustedArchiveHasher::class, 'trusted.archive', new TrustedArchiveHasher()));
        $context->service(ServiceDefinition::instance(LegacyRecordPayloadValidator::class, 'trusted.archive', new LegacyRecordPayloadValidator()));
        $context->service(ServiceDefinition::factory(TrustedArchiveSealer::class, 'trusted.archive', [DatabaseConnectionProvider::class, TrustedArchiveHasher::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): TrustedArchiveSealer => new TrustedArchiveSealer(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, TrustedArchiveHasher::class)))));
        $context->service(ServiceDefinition::factory(P8MaintenanceTask::class, 'trusted.archive', [RecordPassportProjector::class, TrustedArchiveSealer::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): P8MaintenanceTask => new P8MaintenanceTask(ServiceReference::get($resolver, RecordPassportProjector::class), ServiceReference::get($resolver, TrustedArchiveSealer::class)))));
        foreach ([['record_passports.project','Project issued certificates into private Person passports.',60],['trusted_archive.seal_pending','Seal bounded issued-certificate archive records.',60],['trusted_archive.reconcile','Verify bounded trusted-archive hash chains.',900]] as [$id,$description,$interval]) $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id),$description,new FixedIntervalSchedule($interval),P8MaintenanceTask::class,120,'trusted.archive'));
    }
}
