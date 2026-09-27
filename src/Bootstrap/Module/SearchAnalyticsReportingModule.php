<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SearchAnalytics\Application\ScheduledP11MaintenanceTask;
use Qmdb\Modules\SearchAnalytics\Domain\ArabicSearchNormalizer;
use Qmdb\Modules\SearchAnalytics\Domain\DeterministicExportFormatter;
use Qmdb\Modules\SearchAnalytics\Domain\PrivacyDisclosurePolicy;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\PrivateExportArtifactStore;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\P11MaintenanceService;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\P11ExportDeliveryService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\SearchAnalytics\Interface\Console\CompetitionP11ProductionReadinessConsoleCommand;
use Qmdb\Modules\SearchAnalytics\Interface\Console\CompetitionP11ProductionSmokeConsoleCommand;
use Qmdb\Modules\SearchAnalytics\Interface\Console\CompetitionP11VerifyConsoleCommand;
use Qmdb\Modules\SearchAnalytics\Interface\Http\P11PortalController;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class SearchAnalyticsReportingModule implements Module
{
    private const string ID = 'search.analytics_reporting';

    public function __construct(private string $projectRoot)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('community.recitation_clips'), new ModuleId('competition.result_publication'),
            new ModuleId('certificate.issuance'), new ModuleId('security.authorization'),
            new ModuleId('identity.multifactor'), new ModuleId('security.audit'),
            new ModuleId('tenancy.context'), new ModuleId('identity.sessions'),
            new ModuleId('foundation.presentation'), new ModuleId('foundation.database'),
            new ModuleId('foundation.background'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(ArabicSearchNormalizer::class, self::ID, new ArabicSearchNormalizer()));
        $context->service(ServiceDefinition::instance(PrivacyDisclosurePolicy::class, self::ID, new PrivacyDisclosurePolicy()));
        $context->service(ServiceDefinition::instance(DeterministicExportFormatter::class, self::ID, new DeterministicExportFormatter()));
        $context->service(ServiceDefinition::instance(PrivateExportArtifactStore::class, self::ID, new PrivateExportArtifactStore($this->projectRoot)));
        $context->service(ServiceDefinition::factory(
            MySqlP11Repository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlP11Repository => new MySqlP11Repository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            P11MaintenanceService::class,
            self::ID,
            [DatabaseConnectionProvider::class, PrivacyDisclosurePolicy::class, DeterministicExportFormatter::class,
                PrivateExportArtifactStore::class, ArabicSearchNormalizer::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P11MaintenanceService => new P11MaintenanceService(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, PrivacyDisclosurePolicy::class),
                ServiceReference::get($resolver, DeterministicExportFormatter::class),
                ServiceReference::get($resolver, PrivateExportArtifactStore::class),
                ServiceReference::get($resolver, ArabicSearchNormalizer::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            P11ExportDeliveryService::class,
            self::ID,
            [DatabaseConnectionProvider::class, PrivateExportArtifactStore::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P11ExportDeliveryService => new P11ExportDeliveryService(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, PrivateExportArtifactStore::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledP11MaintenanceTask::class,
            self::ID,
            [P11MaintenanceService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduledP11MaintenanceTask => new ScheduledP11MaintenanceTask(
                ServiceReference::get($resolver, P11MaintenanceService::class),
            )),
        ));
        foreach (
            [
            ['analytics.snapshots.process', 'Process bounded due analytics snapshots.', 300, 180],
            ['analytics.snapshots.reconcile', 'Recover expired analytics snapshot leases.', 900, 180],
            ['reports.process', 'Generate bounded approved report runs and private exports.', 60, 180],
            ['reports.reconcile', 'Recover expired report generation leases.', 900, 180],
            ['exports.cleanup', 'Remove expired private export artifacts.', 900, 180],
            ] as [$id, $description, $interval, $lease]
        ) {
            $context->scheduledTask(new ScheduledTaskRegistration(
                new ScheduledTaskId($id),
                $description,
                new FixedIntervalSchedule($interval),
                ScheduledP11MaintenanceTask::class,
                $lease,
                self::ID,
            ));
        }
        $context->service(ServiceDefinition::factory(
            P11PortalController::class,
            self::ID,
            [MySqlP11Repository::class, ArabicSearchNormalizer::class, AuthenticatedRequestGuard::class,
                TenantContextRequiredGuard::class, IdentityCsrf::class, IdentityAccessView::class,
                P11ExportDeliveryService::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P11PortalController => new P11PortalController(
                ServiceReference::get($resolver, MySqlP11Repository::class),
                ServiceReference::get($resolver, ArabicSearchNormalizer::class),
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, P11ExportDeliveryService::class),
                ServiceReference::get($resolver, Psr17Factory::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP11VerifyConsoleCommand::class,
            self::ID,
            [MySqlP11Repository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP11VerifyConsoleCommand => new CompetitionP11VerifyConsoleCommand(ServiceReference::get($resolver, MySqlP11Repository::class))),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP11ProductionReadinessConsoleCommand::class,
            self::ID,
            [MySqlP11Repository::class, ScheduledTaskMap::class, ApplicationConfiguration::class, PrivateExportArtifactStore::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP11ProductionReadinessConsoleCommand => new CompetitionP11ProductionReadinessConsoleCommand(
                ServiceReference::get($resolver, MySqlP11Repository::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, ApplicationConfiguration::class),
                ServiceReference::get($resolver, PrivateExportArtifactStore::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP11ProductionSmokeConsoleCommand::class,
            self::ID,
            [MySqlP11Repository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP11ProductionSmokeConsoleCommand => new CompetitionP11ProductionSmokeConsoleCommand(ServiceReference::get($resolver, MySqlP11Repository::class))),
        ));
    }
}
