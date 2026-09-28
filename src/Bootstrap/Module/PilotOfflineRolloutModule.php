<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\PilotOfflineRollout\Application\P13MaintenanceService;
use Qmdb\Modules\PilotOfflineRollout\Application\P13AdministrativeMutationService;
use Qmdb\Modules\PilotOfflineRollout\Application\P13MutationRequestParser;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\MappedOfflineOperationHandler;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\OfflineAuthoritativeContextFactory;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationDispatcher;
use Qmdb\Modules\PilotOfflineRollout\Application\P13NativeOfflineOperationAdapter;
use Qmdb\Modules\PilotOfflineRollout\Application\P6OfflineScoreDraftAdapter;
use Qmdb\Modules\PilotOfflineRollout\Application\P6OfflineScoreSheetSubmissionAdapter;
use Qmdb\Modules\PilotOfflineRollout\Application\P7OfflineParticipantOperationAdapter;
use Qmdb\Modules\PilotOfflineRollout\Application\ScheduledP13MaintenanceTask;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveParticipantWorkflowService;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6WorkflowService;
use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetService;
use Qmdb\Modules\PilotOfflineRollout\Domain\CanonicalJson;
use Qmdb\Modules\PilotOfflineRollout\Domain\DeviceRequestSignature;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineCryptographicMaterial;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Domain\PilotRolloutLifecycle;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Modules\PilotOfflineRollout\Interface\Console\CompetitionP13VerifyConsoleCommand;
use Qmdb\Modules\PilotOfflineRollout\Interface\Console\CompetitionP13ProductionReadinessConsoleCommand;
use Qmdb\Modules\PilotOfflineRollout\Interface\Console\CompetitionP13ProductionSmokeConsoleCommand;
use Qmdb\Modules\PilotOfflineRollout\Interface\Http\P13PortalController;
use Qmdb\Modules\PilotOfflineRollout\Interface\Http\OfflineProtocolController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Time\Clock;

final readonly class PilotOfflineRolloutModule implements Module
{
    private const string ID = 'pilot.offline_rollout';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('production.hardening'), new ModuleId('search.analytics_reporting'),
            new ModuleId('competition.live_operations'), new ModuleId('competition.scoring'),
            new ModuleId('competition.configuration'), new ModuleId('quran.reference_governance'),
            new ModuleId('security.authorization'), new ModuleId('security.audit'),
            new ModuleId('identity.multifactor'), new ModuleId('identity.sessions'),
            new ModuleId('tenancy.context'), new ModuleId('foundation.presentation'),
            new ModuleId('foundation.database'), new ModuleId('foundation.background'),
            new ModuleId('foundation.observability'), new ModuleId('foundation.core'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(CanonicalJson::class, self::ID, new CanonicalJson()));
        $context->service(ServiceDefinition::instance(DeviceRequestSignature::class, self::ID, new DeviceRequestSignature()));
        $context->service(ServiceDefinition::instance(OfflineOperationPolicy::class, self::ID, new OfflineOperationPolicy()));
        $context->service(ServiceDefinition::instance(PilotRolloutLifecycle::class, self::ID, new PilotRolloutLifecycle()));
        $context->service(ServiceDefinition::factory(
            OfflineCryptographicMaterial::class,
            self::ID,
            [EnvironmentVariables::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): OfflineCryptographicMaterial => new OfflineCryptographicMaterial(ServiceReference::get($resolver, EnvironmentVariables::class))),
        ));
        $context->service(ServiceDefinition::factory(
            OfflinePackageCryptography::class,
            self::ID,
            [OfflineCryptographicMaterial::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): OfflinePackageCryptography => ServiceReference::get($resolver, OfflineCryptographicMaterial::class)->cryptography()),
        ));
        $context->service(ServiceDefinition::factory(
            OfflineAuthoritativeContextFactory::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): OfflineAuthoritativeContextFactory => new OfflineAuthoritativeContextFactory(ServiceReference::get($resolver, DatabaseConnectionProvider::class))),
        ));
        $context->service(ServiceDefinition::factory(P7OfflineParticipantOperationAdapter::class, self::ID, [CompetitionLiveParticipantWorkflowService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): P7OfflineParticipantOperationAdapter => new P7OfflineParticipantOperationAdapter(ServiceReference::get($resolver, CompetitionLiveParticipantWorkflowService::class)))));
        $context->service(ServiceDefinition::factory(P6OfflineScoreDraftAdapter::class, self::ID, [CompetitionScoreSheetService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): P6OfflineScoreDraftAdapter => new P6OfflineScoreDraftAdapter(ServiceReference::get($resolver, CompetitionScoreSheetService::class)))));
        $context->service(ServiceDefinition::factory(P6OfflineScoreSheetSubmissionAdapter::class, self::ID, [CompetitionP6WorkflowService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): P6OfflineScoreSheetSubmissionAdapter => new P6OfflineScoreSheetSubmissionAdapter(ServiceReference::get($resolver, CompetitionP6WorkflowService::class)))));
        $context->service(ServiceDefinition::factory(P13NativeOfflineOperationAdapter::class, self::ID, [SecurityAuditEventAppender::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): P13NativeOfflineOperationAdapter => new P13NativeOfflineOperationAdapter(ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(
            OfflineOperationDispatcher::class,
            self::ID,
            [P7OfflineParticipantOperationAdapter::class, P6OfflineScoreDraftAdapter::class, P6OfflineScoreSheetSubmissionAdapter::class, P13NativeOfflineOperationAdapter::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): OfflineOperationDispatcher {
                $p7 = ServiceReference::get($resolver, P7OfflineParticipantOperationAdapter::class);
                $draft = ServiceReference::get($resolver, P6OfflineScoreDraftAdapter::class);
                $submission = ServiceReference::get($resolver, P6OfflineScoreSheetSubmissionAdapter::class);
                $native = ServiceReference::get($resolver, P13NativeOfflineOperationAdapter::class);
                $handlers = [];
                foreach (['PARTICIPANT_CHECK_IN', 'PARTICIPANT_ABSENT', 'PARTICIPANT_CALLED', 'PARTICIPANT_READY', 'PERFORMANCE_STARTED', 'PERFORMANCE_INTERRUPTED', 'PERFORMANCE_RESUMED', 'PERFORMANCE_COMPLETED'] as $operation) {
                    $handlers[] = new MappedOfflineOperationHandler($operation, 'P7_LIVE', $p7->apply(...));
                }
                $handlers[] = new MappedOfflineOperationHandler('SCORE_DRAFT_SAVED', 'P6_SCORING', $draft->apply(...));
                $handlers[] = new MappedOfflineOperationHandler('SCORE_SHEET_SUBMITTED', 'P6_SCORING', $submission->apply(...));
                foreach (['VENUE_INCIDENT_RECORDED', 'OPERATIONAL_NOTE_RECORDED', 'JUDGE_ACKNOWLEDGEMENT_RECORDED'] as $operation) {
                    $handlers[] = new MappedOfflineOperationHandler($operation, 'P13_OFFLINE', $native->apply(...));
                }

                return new OfflineOperationDispatcher($handlers);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlPilotOfflineRolloutRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class, CanonicalJson::class, OfflinePackageCryptography::class, OfflineOperationPolicy::class, PilotRolloutLifecycle::class, OfflineOperationDispatcher::class, OfflineAuthoritativeContextFactory::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlPilotOfflineRolloutRepository => new MySqlPilotOfflineRolloutRepository(
                ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                ServiceReference::get($resolver, CanonicalJson::class),
                ServiceReference::get($resolver, OfflinePackageCryptography::class),
                ServiceReference::get($resolver, OfflineOperationPolicy::class),
                ServiceReference::get($resolver, PilotRolloutLifecycle::class),
                ServiceReference::get($resolver, OfflineOperationDispatcher::class),
                ServiceReference::get($resolver, OfflineAuthoritativeContextFactory::class),
                ServiceReference::get($resolver, TransactionManager::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            P13MaintenanceService::class,
            self::ID,
            [MySqlPilotOfflineRolloutRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P13MaintenanceService => new P13MaintenanceService(ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class))),
        ));
        $context->service(ServiceDefinition::instance(P13MutationRequestParser::class, self::ID, new P13MutationRequestParser()));
        $context->service(ServiceDefinition::instance(P13RouteRuntimeCatalog::class, self::ID, new P13RouteRuntimeCatalog()));
        $context->service(ServiceDefinition::factory(
            P13AdministrativeMutationService::class,
            self::ID,
            [MySqlPilotOfflineRolloutRepository::class, P13MutationRequestParser::class, SecurityAuditEventAppender::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P13AdministrativeMutationService => new P13AdministrativeMutationService(ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class), ServiceReference::get($resolver, P13MutationRequestParser::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, Clock::class))),
        ));
        $context->service(ServiceDefinition::factory(
            P13PortalController::class,
            self::ID,
            [MySqlPilotOfflineRolloutRepository::class, P13AdministrativeMutationService::class, AuthenticatedRequestGuard::class, TenantContextRequiredGuard::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, Clock::class, IdentityCsrf::class, IdentityAccessView::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P13PortalController => new P13PortalController(
                ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class),
                ServiceReference::get($resolver, P13AdministrativeMutationService::class),
                ServiceReference::get($resolver, AuthenticatedRequestGuard::class),
                ServiceReference::get($resolver, TenantContextRequiredGuard::class),
                ServiceReference::get($resolver, AuthorizationRequirementGuard::class),
                ServiceReference::get($resolver, StepUpGuard::class),
                ServiceReference::get($resolver, IdentityRateLimiter::class),
                ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, IdentityCsrf::class),
                ServiceReference::get($resolver, IdentityAccessView::class),
                ServiceReference::get($resolver, TransactionManager::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            OfflineProtocolController::class,
            self::ID,
            [MySqlPilotOfflineRolloutRepository::class, DeviceRequestSignature::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): OfflineProtocolController => new OfflineProtocolController(ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class), ServiceReference::get($resolver, DeviceRequestSignature::class), ServiceReference::get($resolver, Psr17Factory::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledP13MaintenanceTask::class,
            self::ID,
            [P13MaintenanceService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduledP13MaintenanceTask => new ScheduledP13MaintenanceTask(ServiceReference::get($resolver, P13MaintenanceService::class))),
        ));
        foreach (
            [
            ['pilot.readiness.reconcile', 'Reconcile bounded pilot-site readiness evidence.', 300, 180],
            ['pilot.health.snapshot', 'Capture privacy-safe pilot health evidence.', 300, 180],
            ['rollout.waves.process', 'Process explicitly approved bounded rollout waves.', 300, 180],
            ['rollout.health.snapshot', 'Capture privacy-safe rollout-wave health evidence.', 300, 180],
            ['offline.packages.process', 'Process governed offline package preparation jobs.', 30, 120],
            ['offline.packages.expire', 'Expire offline packages whose bounded authority ended.', 60, 120],
            ['offline.packages.reconcile', 'Reconcile offline package state and artifacts.', 300, 180],
            ['offline.devices.reconcile', 'Reconcile offline device status and key validity.', 300, 180],
            ['offline.sync.reconcile', 'Recover expired offline synchronization sessions.', 60, 120],
            ['offline.conflicts.notify', 'Create safe reminders for unresolved offline conflicts.', 300, 180],
            ['offline.receipts.reconcile', 'Verify immutable offline synchronization receipts.', 300, 180],
            ] as [$id, $description, $interval, $lease]
        ) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), ScheduledP13MaintenanceTask::class, $lease, self::ID));
        }
        $commandDependencies = [
            MySqlPilotOfflineRolloutRepository::class,
            ScheduledTaskMap::class,
            OfflinePackageCryptography::class,
            ApplicationConfiguration::class,
            OfflineOperationDispatcher::class,
            P13RouteRuntimeCatalog::class,
            P13MaintenanceService::class,
        ];
        $context->service(ServiceDefinition::factory(
            CompetitionP13VerifyConsoleCommand::class,
            self::ID,
            $commandDependencies,
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP13VerifyConsoleCommand => new CompetitionP13VerifyConsoleCommand(
                'competition:p13:verify',
                ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, OfflinePackageCryptography::class),
                ServiceReference::get($resolver, ApplicationConfiguration::class),
                ServiceReference::get($resolver, OfflineOperationDispatcher::class),
                ServiceReference::get($resolver, P13RouteRuntimeCatalog::class),
                ServiceReference::get($resolver, P13MaintenanceService::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP13ProductionReadinessConsoleCommand::class,
            self::ID,
            $commandDependencies,
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP13ProductionReadinessConsoleCommand => new CompetitionP13ProductionReadinessConsoleCommand(
                'competition:p13:production-readiness:verify',
                ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, OfflinePackageCryptography::class),
                ServiceReference::get($resolver, ApplicationConfiguration::class),
                ServiceReference::get($resolver, OfflineOperationDispatcher::class),
                ServiceReference::get($resolver, P13RouteRuntimeCatalog::class),
                ServiceReference::get($resolver, P13MaintenanceService::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            CompetitionP13ProductionSmokeConsoleCommand::class,
            self::ID,
            $commandDependencies,
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP13ProductionSmokeConsoleCommand => new CompetitionP13ProductionSmokeConsoleCommand(
                'competition:p13:production-smoke:verify',
                ServiceReference::get($resolver, MySqlPilotOfflineRolloutRepository::class),
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, OfflinePackageCryptography::class),
                ServiceReference::get($resolver, ApplicationConfiguration::class),
                ServiceReference::get($resolver, OfflineOperationDispatcher::class),
                ServiceReference::get($resolver, P13RouteRuntimeCatalog::class),
                ServiceReference::get($resolver, P13MaintenanceService::class),
            )),
        ));
    }
}
