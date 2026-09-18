<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Console\P2SecurityHardeningVerifyConsoleCommand;
use Qmdb\Bootstrap\Console\P3SecurityHardeningVerifyConsoleCommand;
use Qmdb\Bootstrap\Console\P3PersonRepositorySecurityVerifyConsoleCommand;
use Qmdb\Bootstrap\Security\P2SecurityHardeningVerifier;
use Qmdb\Bootstrap\Security\P3PersonRepositorySecurityVerifier;
use Qmdb\Bootstrap\Security\P3SecurityHardeningVerifier;
use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessCheck;
use Qmdb\Modules\IdentityResolution\Application\PeopleIdentityResolutionReadinessCheck;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationsReadinessCheck;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryReadinessCheck;
use Qmdb\Modules\People\Application\PeopleProfilesReadinessCheck;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantRepositorySecurityVerifier;
use Qmdb\Shared\Background\Console\ScheduleListConsoleCommand;
use Qmdb\Shared\Background\Console\ScheduleRunConsoleCommand;
use Qmdb\Shared\Background\Console\WorkerRunConsoleCommand;
use Qmdb\Shared\Console\Command\AppAboutConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandDispatcher;
use Qmdb\Shared\Console\Command\ConsoleCommandMap;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Command\ConsoleCommandRegistry;
use Qmdb\Shared\Console\Command\SchemaConsoleCommand;
use Qmdb\Shared\Console\Input\ConsoleInputParser;
use Qmdb\Shared\Console\Observability\ConsoleExecutionObserver;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifyConsoleCommand;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;
use Qmdb\Modules\SecurityAuthorization\Interface\Console\AuthorizationVerifyConsoleCommand;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantContextVerifyConsoleCommand;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantRepositorySecurityVerifyConsoleCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Console\PrivilegedAccessVerifyConsoleCommand;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditCheckpointConsoleCommand;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditVerifyConsoleCommand;
use Qmdb\Modules\Geography\Interface\Console\GeographyReferenceVerifyConsoleCommand;
use Qmdb\Modules\People\Interface\Console\PeopleProfilesVerifyConsoleCommand;
use Qmdb\Modules\Organizations\Interface\Console\OrganizationsRegistryVerifyConsoleCommand;
use Qmdb\Modules\OrganizationAffiliations\Interface\Console\OrganizationAffiliationsVerifyConsoleCommand;
use Qmdb\Modules\IdentityResolution\Interface\Console\PeopleIdentityResolutionVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\P4DecompositionVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranGovernanceVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourcesVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourceArtifactRegisterConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranB02ArtifactsVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranReleaseImportConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSearchCorpusImportConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranContentVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSearchArtifactVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSearchCorpusVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranPublicReferenceVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranP4CloseoutVerifyConsoleCommand;
use Qmdb\Modules\CompetitionConfiguration\Interface\Console\CompetitionConfigurationVerifyConsoleCommand;
use Qmdb\Modules\CompetitionConfiguration\Interface\Console\CompetitionP5VerifyConsoleCommand;
use Qmdb\Modules\CompetitionRegistration\Interface\Console\CompetitionRegistrationVerifyConsoleCommand;
use Qmdb\Modules\CompetitionResults\Interface\Console\CompetitionP6VerifyConsoleCommand;
use Qmdb\Modules\CompetitionResults\Interface\Console\CompetitionP6AspectVerifyConsoleCommand;
use Qmdb\Modules\CompetitionResults\Interface\Console\CompetitionP6MaintenanceConsoleCommand;
use Qmdb\Modules\CompetitionLive\Interface\Console\CompetitionP7VerifyConsoleCommand;
use Qmdb\Modules\CompetitionLive\Interface\Console\CompetitionP7CloseoutVerifyConsoleCommand;
use Qmdb\Modules\CompetitionLive\Interface\Console\CompetitionP7ProductionReadinessConsoleCommand;
use Qmdb\Modules\CompetitionLive\Interface\Console\CompetitionP7LiveMaintenanceConsoleCommand;
use Qmdb\Modules\CompetitionLive\Infrastructure\Readiness\CompetitionP7CloseoutReadinessCheck;
use Qmdb\Modules\CompetitionPublication\Interface\Console\CompetitionResultPublicationProjectionConsoleCommand;
use Qmdb\Modules\CompetitionAppealAdjudication\Interface\Console\CompetitionAppealMaintenanceConsoleCommand;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CompetitionP7LiveMaintenanceService;
use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\CompetitionResultPublicationProjectionService;
use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence\CompetitionAppealMaintenanceService;
use Qmdb\Modules\CertificateIssuance\Interface\Console\CompetitionP8VerifyConsoleCommand;
use Qmdb\Modules\MediaCatalog\Interface\Console\CompetitionP9VerifyConsoleCommand;
use Qmdb\Modules\MediaCatalog\Interface\Console\MediaP9RuntimeConsoleCommand;
use Qmdb\Modules\MediaProcessing\Application\MediaScanWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\CompetitionP6MaintenanceService;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class ConsoleFoundationModule implements Module
{
    private const ID = 'foundation.console';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'),
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.background'),
            new ModuleId('security.authorization'),
            new ModuleId('tenancy.context'),
            new ModuleId('security.privileged_access'),
            new ModuleId('security.audit'),
            new ModuleId('application.http'),
            new ModuleId('competition.configuration'),
            new ModuleId('competition.registration'),
            new ModuleId('competition.results'),
            new ModuleId('competition.live_operations'),
            new ModuleId('competition.result_publication'),
            new ModuleId('competition.appeal_adjudication'),
            new ModuleId('certificate.issuance'),
            new ModuleId('record.passport'),
            new ModuleId('trusted.archive'),
            new ModuleId('media.catalog'),
            new ModuleId('media.processing'),
            new ModuleId('reference.geography'),
            new ModuleId('people.profiles'),
            new ModuleId('organizations.registry'),
            new ModuleId('organizations.affiliations'),
            new ModuleId('people.identity_resolution'),
            new ModuleId('quran.reference_governance'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ConsoleInputParser::class,
            self::ID,
            new ConsoleInputParser(),
        ));
        $context->service(ServiceDefinition::factory(
            AppAboutConsoleCommand::class,
            self::ID,
            [Application::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): AppAboutConsoleCommand =>
                new AppAboutConsoleCommand(ServiceReference::get($resolver, Application::class))),
        ));
        $context->service(ServiceDefinition::factory(
            P2SecurityHardeningVerifier::class,
            self::ID,
            [
                AuthorizationCatalogVerifier::class,
                TenantContextSchemaVerifier::class,
                TenantRepositorySecurityVerifier::class,
                PrivilegedAccessSchemaVerifier::class,
                SecurityAuditControlVerifier::class,
                RouteSecurityVerifier::class,
                RouteCollection::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P2SecurityHardeningVerifier =>
                new P2SecurityHardeningVerifier(
                    ServiceReference::get($resolver, AuthorizationCatalogVerifier::class),
                    ServiceReference::get($resolver, TenantContextSchemaVerifier::class),
                    ServiceReference::get($resolver, TenantRepositorySecurityVerifier::class),
                    ServiceReference::get($resolver, PrivilegedAccessSchemaVerifier::class),
                    ServiceReference::get($resolver, SecurityAuditControlVerifier::class),
                    ServiceReference::get($resolver, RouteSecurityVerifier::class),
                    ServiceReference::get($resolver, RouteCollection::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            P2SecurityHardeningVerifyConsoleCommand::class,
            self::ID,
            [P2SecurityHardeningVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P2SecurityHardeningVerifyConsoleCommand =>
                new P2SecurityHardeningVerifyConsoleCommand(
                    ServiceReference::get($resolver, P2SecurityHardeningVerifier::class),
                )),
        ));
        $context->service(ServiceDefinition::instance(
            P3PersonRepositorySecurityVerifier::class,
            self::ID,
            new P3PersonRepositorySecurityVerifier(dirname(__DIR__, 3)),
        ));
        $context->service(ServiceDefinition::factory(
            P3SecurityHardeningVerifier::class,
            self::ID,
            [
                P2SecurityHardeningVerifier::class,
                GeographyReferenceReadinessCheck::class,
                PeopleProfilesReadinessCheck::class,
                OrganizationsRegistryReadinessCheck::class,
                OrganizationAffiliationsReadinessCheck::class,
                PeopleIdentityResolutionReadinessCheck::class,
                P3PersonRepositorySecurityVerifier::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P3SecurityHardeningVerifier =>
                new P3SecurityHardeningVerifier(
                    ServiceReference::get($resolver, P2SecurityHardeningVerifier::class),
                    ServiceReference::get($resolver, GeographyReferenceReadinessCheck::class),
                    ServiceReference::get($resolver, PeopleProfilesReadinessCheck::class),
                    ServiceReference::get($resolver, OrganizationsRegistryReadinessCheck::class),
                    ServiceReference::get($resolver, OrganizationAffiliationsReadinessCheck::class),
                    ServiceReference::get($resolver, PeopleIdentityResolutionReadinessCheck::class),
                    ServiceReference::get($resolver, P3PersonRepositorySecurityVerifier::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            P3SecurityHardeningVerifyConsoleCommand::class,
            self::ID,
            [P3SecurityHardeningVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P3SecurityHardeningVerifyConsoleCommand =>
                new P3SecurityHardeningVerifyConsoleCommand(ServiceReference::get($resolver, P3SecurityHardeningVerifier::class))),
        ));
        $context->service(ServiceDefinition::factory(
            P3PersonRepositorySecurityVerifyConsoleCommand::class,
            self::ID,
            [P3PersonRepositorySecurityVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P3PersonRepositorySecurityVerifyConsoleCommand =>
                new P3PersonRepositorySecurityVerifyConsoleCommand(ServiceReference::get($resolver, P3PersonRepositorySecurityVerifier::class))),
        ));
        $this->registerCommandMap($context);
        $context->service(ServiceDefinition::factory(
            CompetitionP7VerifyConsoleCommand::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7VerifyConsoleCommand => new CompetitionP7VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class))),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP7CloseoutReadinessCheck::class,
            self::ID,
            [DatabaseConnectionProvider::class, ScheduledTaskMap::class, RouteCollection::class, RouteSecurityVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7CloseoutReadinessCheck => new CompetitionP7CloseoutReadinessCheck(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, RouteCollection::class),
                ServiceReference::get($resolver, RouteSecurityVerifier::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP7CloseoutVerifyConsoleCommand::class,
            self::ID,
            [CompetitionP7CloseoutReadinessCheck::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7CloseoutVerifyConsoleCommand => new CompetitionP7CloseoutVerifyConsoleCommand(
                ServiceReference::get($resolver, CompetitionP7CloseoutReadinessCheck::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP7ProductionReadinessConsoleCommand::class,
            self::ID,
            [ApplicationConfiguration::class, DatabaseConnectionProvider::class, ScheduledTaskMap::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7ProductionReadinessConsoleCommand => new CompetitionP7ProductionReadinessConsoleCommand(
                ServiceReference::get($resolver, ApplicationConfiguration::class),
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            ConsoleCommandDispatcher::class,
            self::ID,
            [ConsoleCommandMap::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ConsoleCommandDispatcher =>
                new ConsoleCommandDispatcher(ServiceReference::get($resolver, ConsoleCommandMap::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ConsoleApplication::class,
            self::ID,
            [
                Application::class,
                ConsoleInputParser::class,
                ConsoleCommandMap::class,
                ConsoleCommandDispatcher::class,
                ConsoleExecutionObserver::class,
                ErrorHandlingRuntime::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ConsoleApplication =>
                new ConsoleApplication(
                    application: ServiceReference::get($resolver, Application::class),
                    executionObserver: ServiceReference::get($resolver, ConsoleExecutionObserver::class),
                    errorHandlingRuntime: ServiceReference::get($resolver, ErrorHandlingRuntime::class),
                    inputParser: ServiceReference::get($resolver, ConsoleInputParser::class),
                    commands: ServiceReference::get($resolver, ConsoleCommandMap::class),
                    dispatcher: ServiceReference::get($resolver, ConsoleCommandDispatcher::class),
                )),
        ));
    }

    private function registerCommandMap(ModuleRegistrationContext $context): void
    {
        $dependencies = [
            AppAboutConsoleCommand::class,
            SchemaConsoleApplication::class,
            ScheduleListConsoleCommand::class,
            ScheduleRunConsoleCommand::class,
            WorkerRunConsoleCommand::class,
            AuthorizationVerifyConsoleCommand::class,
            TenantContextVerifyConsoleCommand::class,
            TenantRepositorySecurityVerifyConsoleCommand::class,
            PrivilegedAccessVerifyConsoleCommand::class,
            SecurityAuditVerifyConsoleCommand::class,
            RouteSecurityVerifyConsoleCommand::class,
            P2SecurityHardeningVerifyConsoleCommand::class,
            P3SecurityHardeningVerifyConsoleCommand::class,
            P3PersonRepositorySecurityVerifyConsoleCommand::class,
            SecurityAuditCheckpointConsoleCommand::class,
            GeographyReferenceVerifyConsoleCommand::class,
            PeopleProfilesVerifyConsoleCommand::class,
            OrganizationsRegistryVerifyConsoleCommand::class,
            OrganizationAffiliationsVerifyConsoleCommand::class,
            PeopleIdentityResolutionVerifyConsoleCommand::class,
            P4DecompositionVerifyConsoleCommand::class,
            QuranSourcesVerifyConsoleCommand::class,
            QuranSourceArtifactRegisterConsoleCommand::class,
            QuranGovernanceVerifyConsoleCommand::class,
            QuranB02ArtifactsVerifyConsoleCommand::class,
            QuranReleaseImportConsoleCommand::class,
            QuranSearchCorpusImportConsoleCommand::class,
            QuranContentVerifyConsoleCommand::class,
            QuranSearchArtifactVerifyConsoleCommand::class,
            QuranSearchCorpusVerifyConsoleCommand::class,
            QuranPublicReferenceVerifyConsoleCommand::class,
            QuranP4CloseoutVerifyConsoleCommand::class,
            CompetitionConfigurationVerifyConsoleCommand::class,
            CompetitionP5VerifyConsoleCommand::class,
            CompetitionRegistrationVerifyConsoleCommand::class,
            CompetitionP6VerifyConsoleCommand::class,
            CompetitionP7VerifyConsoleCommand::class,
            CompetitionP7CloseoutVerifyConsoleCommand::class,
            CompetitionP7ProductionReadinessConsoleCommand::class,
            CompetitionP8VerifyConsoleCommand::class,
            CompetitionP9VerifyConsoleCommand::class,
            MediaScanWorker::class,
            MediaProcessingWorker::class,
            \Qmdb\Modules\MediaProcessing\Application\MediaMaintenanceWorker::class,
            \Qmdb\Modules\MediaProcessing\Application\MediaRuntimeReadiness::class,
            EnvironmentVariables::class,
            CompetitionP6MaintenanceService::class,
            CompetitionP7LiveMaintenanceService::class,
            CompetitionResultPublicationProjectionService::class,
            CompetitionAppealMaintenanceService::class,
            DatabaseConnectionProvider::class,
            ScheduledTaskMap::class,
        ];
        $context->service(ServiceDefinition::factory(
            ConsoleCommandMap::class,
            self::ID,
            $dependencies,
            new ClosureServiceFactory(static function (DependencyResolver $resolver): ConsoleCommandMap {
                $schema = ServiceReference::get($resolver, SchemaConsoleApplication::class);
                $registry = new ConsoleCommandRegistry();
                $registry->register(ServiceReference::get($resolver, AppAboutConsoleCommand::class));
                foreach (self::schemaCommands() as $name => [$description, $options]) {
                    $registry->register(new SchemaConsoleCommand(
                        new ConsoleCommandName($name),
                        $description,
                        $schema,
                        $options,
                    ));
                }
                $registry->register(ServiceReference::get($resolver, ScheduleListConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, ScheduleRunConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, WorkerRunConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, AuthorizationVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, TenantContextVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, TenantRepositorySecurityVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, PrivilegedAccessVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, SecurityAuditVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, RouteSecurityVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, P2SecurityHardeningVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, P3SecurityHardeningVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, P3PersonRepositorySecurityVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, SecurityAuditCheckpointConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, GeographyReferenceVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, PeopleProfilesVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, OrganizationsRegistryVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, OrganizationAffiliationsVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, PeopleIdentityResolutionVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, P4DecompositionVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranSourcesVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranSourceArtifactRegisterConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranGovernanceVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranB02ArtifactsVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranReleaseImportConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranSearchCorpusImportConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranContentVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranSearchArtifactVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranSearchCorpusVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranPublicReferenceVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, QuranP4CloseoutVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionConfigurationVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP5VerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionRegistrationVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP6VerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP7VerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP7CloseoutVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP7ProductionReadinessConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP8VerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, CompetitionP9VerifyConsoleCommand::class));
                foreach (
                    [
                    ['media:scans:process', 'scans:process'], ['media:scans:verify', 'scans:verify'], ['media:processing:process', 'processing:process'], ['media:processing:verify', 'processing:verify'], ['media:assets:verify', 'assets:verify'], ['media:assets:reconcile', 'assets:reconcile'], ['media:storage:verify', 'storage:verify'], ['media:storage:reconcile', 'storage:reconcile'], ['media:staging:cleanup', 'staging:cleanup'], ['media:uploads:expire', 'uploads:expire'], ['competition:p9:production-readiness:verify', 'production-readiness'], ['competition:p9:production-smoke:verify', 'production-smoke'],
                    ] as [$name, $operation]
                ) {
                    $registry->register(new MediaP9RuntimeConsoleCommand($name, $operation, ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, ScheduledTaskMap::class), ServiceReference::get($resolver, MediaScanWorker::class), ServiceReference::get($resolver, MediaProcessingWorker::class), ServiceReference::get($resolver, EnvironmentVariables::class), ServiceReference::get($resolver, \Qmdb\Modules\MediaProcessing\Application\MediaMaintenanceWorker::class), ServiceReference::get($resolver, \Qmdb\Modules\MediaProcessing\Application\MediaRuntimeReadiness::class)));
                }
                $p7LiveMaintenance = ServiceReference::get($resolver, CompetitionP7LiveMaintenanceService::class);
                foreach (
                    [
                    ['competition:live:project', 'live-project'],
                    ['competition:live:rebuild', 'live-rebuild'],
                    ['competition:live:reconcile', 'live-reconcile'],
                    ['competition:live:outbox-retry', 'live-outbox-retry'],
                    ] as [$name, $operation]
                ) {
                    $registry->register(new CompetitionP7LiveMaintenanceConsoleCommand($name, $operation, $p7LiveMaintenance));
                }
                $publicationProjections = ServiceReference::get($resolver, CompetitionResultPublicationProjectionService::class);
                foreach (
                    [
                    ['competition:result-publications:process', 'process'],
                    ['competition:result-publications:verify', 'verify'],
                    ['competition:result-publications:rebuild', 'rebuild'],
                    ['competition:result-publications:reconcile', 'reconcile'],
                    ] as [$name, $operation]
                ) {
                    $registry->register(new CompetitionResultPublicationProjectionConsoleCommand($name, $operation, $publicationProjections));
                }
                $appealMaintenance = ServiceReference::get($resolver, CompetitionAppealMaintenanceService::class);
                foreach (
                    [
                    ['competition:appeals:process', 'process'],
                    ] as [$name, $operation]
                ) {
                    $registry->register(new CompetitionAppealMaintenanceConsoleCommand($name, $operation, $appealMaintenance));
                }
                $maintenance = ServiceReference::get($resolver, CompetitionP6MaintenanceService::class);
                foreach (
                    [
                    ['competition:rounds:process', 'rounds'],
                    ['competition:score-sheets:remind', 'reminders'],
                    ['competition:appeal-windows:process', 'appeal-windows'],
                    ['competition:score-sheets:reconcile', 'score-reconciliation'],
                    ['competition:results:reconcile', 'result-reconciliation'],
                    ] as [$name, $operation]
                ) {
                    $registry->register(new CompetitionP6MaintenanceConsoleCommand($name, $operation, $maintenance));
                }
                foreach (
                    [
                    ['competition:judging:verify', 'Verify P6 judging and panel integrity.'],
                    ['competition:scoring:verify', 'Verify P6 fixed-point scoring integrity.'],
                    ['competition:results:verify', 'Verify P6 result and ranking integrity.'],
                    ['competition:appeals:verify', 'Verify P6 controlled appeal integrity.'],
                    ] as [$name, $description]
                ) {
                    $registry->register(new CompetitionP6AspectVerifyConsoleCommand(
                        $name,
                        $description,
                        ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                        ServiceReference::get($resolver, ScheduledTaskMap::class),
                    ));
                }

                return $registry->build();
            }),
        ));
    }

    /** @return array<string, array{string, list<string>}> */
    private static function schemaCommands(): array
    {
        return [
            'db:schema:install' => ['Install and verify schema ledger metadata.', []],
            'db:schema:verify' => ['Verify schema metadata and migration state.', []],
            'db:migrate:plan' => ['Display the deterministic migration plan.', []],
            'db:migrate' => ['Apply pending explicitly registered migrations.', []],
            'db:migrate:status' => ['Display deterministic migration status.', []],
            'db:migrate:rollback' => [
                'Roll back one confirmed migration in local/test only.',
                ['migration', 'confirm'],
            ],
            'db:seed' => ['Apply pending explicitly registered seeds.', []],
            'db:seed:status' => ['Display deterministic seed status.', []],
        ];
    }
}
