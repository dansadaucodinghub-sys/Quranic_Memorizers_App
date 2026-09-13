<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\CompetitionLive\Domain\LiveParticipantLifecycle;
use Qmdb\Modules\CompetitionLive\Domain\LiveSessionLifecycle;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveRuntimeRepository;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveSessionWorkflowService;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveParticipantWorkflowService;
use Qmdb\Modules\CompetitionLive\Application\CompetitionP7LiveScheduledTask;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CompetitionP7LiveMaintenanceService;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLivePublicReadRepository;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\MySqlCompetitionLiveRuntimeRepository;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\MySqlCompetitionLivePublicReadRepository;
use Qmdb\Modules\CompetitionLive\Interface\Http\CompetitionPublicLiveController;
use Qmdb\Modules\CompetitionLive\Interface\Http\CompetitionLiveSessionWorkflowController;
use Qmdb\Modules\CompetitionLive\Interface\Http\CompetitionLiveParticipantWorkflowController;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
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
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;

final readonly class CompetitionLiveModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.live_operations');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.configuration'),
            new ModuleId('competition.registration'),
            new ModuleId('competition.judging'),
            new ModuleId('competition.scoring'),
            new ModuleId('competition.results'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
            new ModuleId('identity.sessions'),
            new ModuleId('identity.multifactor'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MySqlCompetitionLiveRuntimeRepository::class, 'competition.live_operations', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionLiveRuntimeRepository => new MySqlCompetitionLiveRuntimeRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionLiveRuntimeRepository::class, MySqlCompetitionLiveRuntimeRepository::class);
        $context->service(ServiceDefinition::factory(CompetitionP7LiveMaintenanceService::class, 'competition.live_operations', [DatabaseConnectionProvider::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7LiveMaintenanceService => new CompetitionP7LiveMaintenanceService(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP7LiveScheduledTask::class, 'competition.live_operations', [CompetitionP7LiveMaintenanceService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP7LiveScheduledTask => new CompetitionP7LiveScheduledTask(ServiceReference::get($resolver, CompetitionP7LiveMaintenanceService::class)))));
        $context->service(ServiceDefinition::factory(MySqlCompetitionLivePublicReadRepository::class, 'competition.live_operations', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionLivePublicReadRepository => new MySqlCompetitionLivePublicReadRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionLivePublicReadRepository::class, MySqlCompetitionLivePublicReadRepository::class);
        $context->service(ServiceDefinition::instance(LiveSessionLifecycle::class, 'competition.live_operations', new LiveSessionLifecycle()));
        $context->service(ServiceDefinition::instance(LiveParticipantLifecycle::class, 'competition.live_operations', new LiveParticipantLifecycle()));
        $context->service(ServiceDefinition::factory(CompetitionLiveSessionWorkflowService::class, 'competition.live_operations', [CompetitionLiveRuntimeRepository::class, LiveSessionLifecycle::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionLiveSessionWorkflowService => new CompetitionLiveSessionWorkflowService(ServiceReference::get($resolver, CompetitionLiveRuntimeRepository::class), ServiceReference::get($resolver, LiveSessionLifecycle::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionLiveParticipantWorkflowService::class, 'competition.live_operations', [CompetitionLiveRuntimeRepository::class, LiveParticipantLifecycle::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionLiveParticipantWorkflowService => new CompetitionLiveParticipantWorkflowService(ServiceReference::get($resolver, CompetitionLiveRuntimeRepository::class), ServiceReference::get($resolver, LiveParticipantLifecycle::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionPublicLiveController::class, 'competition.live_operations', [CompetitionLivePublicReadRepository::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionPublicLiveController => new CompetitionPublicLiveController(ServiceReference::get($resolver, CompetitionLivePublicReadRepository::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(CompetitionLiveSessionWorkflowController::class, 'competition.live_operations', [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class, IdentityCsrf::class, CompetitionLiveSessionWorkflowService::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionLiveSessionWorkflowController => new CompetitionLiveSessionWorkflowController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CompetitionLiveSessionWorkflowService::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(CompetitionLiveParticipantWorkflowController::class, 'competition.live_operations', [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class, IdentityCsrf::class, CompetitionLiveParticipantWorkflowService::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionLiveParticipantWorkflowController => new CompetitionLiveParticipantWorkflowController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CompetitionLiveParticipantWorkflowService::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        foreach ([
            ['competition.live.project', 'Project bounded P7 live-event outbox messages.', 30],
            ['competition.live.reconcile', 'Reconcile bounded P7 live-event hash chains.', 900],
            ['competition.live.outbox.retry', 'Release expired P7 live-projection outbox leases.', 60],
        ] as [$id, $description, $interval]) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), CompetitionP7LiveScheduledTask::class, 120, 'competition.live_operations'));
        }
    }
}
