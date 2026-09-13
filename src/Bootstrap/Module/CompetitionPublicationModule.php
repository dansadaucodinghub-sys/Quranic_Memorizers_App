<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionPublication\Domain\ResultPublicationLifecycle;
use Qmdb\Modules\CompetitionPublication\Application\CompetitionResultPublicationRepository;
use Qmdb\Modules\CompetitionPublication\Application\CompetitionResultPublicationWorkflowService;
use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\MySqlCompetitionResultPublicationRepository;
use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\CompetitionResultPublicationProjectionService;
use Qmdb\Modules\CompetitionPublication\Application\CompetitionResultPublicationProjectionScheduledTask;
use Qmdb\Modules\CompetitionPublication\Interface\Http\CompetitionResultPublicationWorkflowController;
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
use Nyholm\Psr7\Factory\Psr17Factory;

/** Result publication is distinct from immutable P6 result calculation. */
final readonly class CompetitionPublicationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.result_publication');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.results'),
            new ModuleId('competition.live_operations'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ResultPublicationLifecycle::class,
            'competition.result_publication',
            new ResultPublicationLifecycle(),
        ));
        $context->service(ServiceDefinition::factory(MySqlCompetitionResultPublicationRepository::class, 'competition.result_publication', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionResultPublicationRepository => new MySqlCompetitionResultPublicationRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionResultPublicationRepository::class, MySqlCompetitionResultPublicationRepository::class);
        $context->service(ServiceDefinition::factory(CompetitionResultPublicationProjectionService::class, 'competition.result_publication', [DatabaseConnectionProvider::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultPublicationProjectionService => new CompetitionResultPublicationProjectionService(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionResultPublicationProjectionScheduledTask::class, 'competition.result_publication', [CompetitionResultPublicationProjectionService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultPublicationProjectionScheduledTask => new CompetitionResultPublicationProjectionScheduledTask(ServiceReference::get($resolver, CompetitionResultPublicationProjectionService::class)))));
        $context->service(ServiceDefinition::factory(CompetitionResultPublicationWorkflowService::class, 'competition.result_publication', [CompetitionResultPublicationRepository::class, ResultPublicationLifecycle::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultPublicationWorkflowService => new CompetitionResultPublicationWorkflowService(ServiceReference::get($resolver, CompetitionResultPublicationRepository::class), ServiceReference::get($resolver, ResultPublicationLifecycle::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionResultPublicationWorkflowController::class, 'competition.result_publication', [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class, IdentityCsrf::class, CompetitionResultPublicationWorkflowService::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultPublicationWorkflowController => new CompetitionResultPublicationWorkflowController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CompetitionResultPublicationWorkflowService::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        foreach ([
            ['competition.result_publications.process', 'Process bounded P7 result-publication projections.', 30],
            ['competition.result_publications.reconcile', 'Reconcile bounded P7 result-publication projections.', 900],
        ] as [$id, $description, $interval]) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), CompetitionResultPublicationProjectionScheduledTask::class, 120, 'competition.result_publication'));
        }
    }
}
