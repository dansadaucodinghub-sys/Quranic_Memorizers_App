<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionResults\Interface\Console\CompetitionP6VerifyConsoleCommand;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6RuntimeRepository;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\CompetitionP6MaintenanceService;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6ScheduledMaintenanceTask;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6WorkflowService;
use Qmdb\Modules\CompetitionResults\Application\CompetitionResultCalculationRepository;
use Qmdb\Modules\CompetitionResults\Application\CompetitionResultCalculationService;
use Qmdb\Modules\CompetitionResults\Domain\AppealLifecycle;
use Qmdb\Modules\CompetitionResults\Domain\CompetitionResultCalculator;
use Qmdb\Modules\CompetitionResults\Domain\DeterministicRanking;
use Qmdb\Modules\CompetitionResults\Domain\PanelScoreAggregator;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunLifecycle;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\MySqlCompetitionP6RuntimeRepository;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\MySqlCompetitionResultCalculationRepository;
use Qmdb\Modules\CompetitionResults\Interface\Http\CompetitionP6WorkflowController;
use Qmdb\Modules\CompetitionResults\Interface\Http\CompetitionPublicResultsController;
use Qmdb\Modules\CompetitionJudging\Domain\RoundLifecycle;
use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetService;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetLifecycle;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Nyholm\Psr7\Factory\Psr17Factory;

final readonly class CompetitionResultsModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.results');
    }
    public function dependencies(): array
    {
        return [new ModuleId('competition.scoring'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('security.web'), new ModuleId('tenancy.context')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MySqlCompetitionP6RuntimeRepository::class, 'competition.results', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionP6RuntimeRepository => new MySqlCompetitionP6RuntimeRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionP6RuntimeRepository::class, MySqlCompetitionP6RuntimeRepository::class);
        $context->service(ServiceDefinition::factory(CompetitionP6MaintenanceService::class, 'competition.results', [DatabaseConnectionProvider::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6MaintenanceService => new CompetitionP6MaintenanceService(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP6ScheduledMaintenanceTask::class, 'competition.results', [CompetitionP6MaintenanceService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6ScheduledMaintenanceTask => new CompetitionP6ScheduledMaintenanceTask(ServiceReference::get($resolver, CompetitionP6MaintenanceService::class)))));
        $context->service(ServiceDefinition::factory(MySqlCompetitionResultCalculationRepository::class, 'competition.results', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCompetitionResultCalculationRepository => new MySqlCompetitionResultCalculationRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CompetitionResultCalculationRepository::class, MySqlCompetitionResultCalculationRepository::class);
        $context->service(ServiceDefinition::instance(RoundLifecycle::class, 'competition.results', new RoundLifecycle()));
        $context->service(ServiceDefinition::instance(ScoreSheetLifecycle::class, 'competition.results', new ScoreSheetLifecycle()));
        $context->service(ServiceDefinition::instance(ResultRunLifecycle::class, 'competition.results', new ResultRunLifecycle()));
        $context->service(ServiceDefinition::instance(AppealLifecycle::class, 'competition.results', new AppealLifecycle()));
        $context->service(ServiceDefinition::instance(PanelScoreAggregator::class, 'competition.results', new PanelScoreAggregator()));
        $context->service(ServiceDefinition::instance(DeterministicRanking::class, 'competition.results', new DeterministicRanking()));
        $context->service(ServiceDefinition::factory(CompetitionResultCalculator::class, 'competition.results', [PanelScoreAggregator::class,DeterministicRanking::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultCalculator => new CompetitionResultCalculator(ServiceReference::get($resolver, PanelScoreAggregator::class), ServiceReference::get($resolver, DeterministicRanking::class)))));
        $context->service(ServiceDefinition::factory(CompetitionResultCalculationService::class, 'competition.results', [CompetitionResultCalculationRepository::class,CompetitionResultCalculator::class,AuthorizationRequirementGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,SecurityAuditEventAppender::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionResultCalculationService => new CompetitionResultCalculationService(ServiceReference::get($resolver, CompetitionResultCalculationRepository::class), ServiceReference::get($resolver, CompetitionResultCalculator::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP6WorkflowService::class, 'competition.results', [CompetitionP6RuntimeRepository::class,AuthorizationRequirementGuard::class,StepUpGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,SecurityAuditEventAppender::class,TransactionManager::class,Clock::class,RoundLifecycle::class,ScoreSheetLifecycle::class,ResultRunLifecycle::class,AppealLifecycle::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6WorkflowService => new CompetitionP6WorkflowService(ServiceReference::get($resolver, CompetitionP6RuntimeRepository::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class), ServiceReference::get($resolver, RoundLifecycle::class), ServiceReference::get($resolver, ScoreSheetLifecycle::class), ServiceReference::get($resolver, ResultRunLifecycle::class), ServiceReference::get($resolver, AppealLifecycle::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP6WorkflowController::class, 'competition.results', [AuthenticatedRequestGuard::class,\Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class,IdentityCsrf::class,CompetitionP6WorkflowService::class,CompetitionResultCalculationService::class,CompetitionScoreSheetService::class,Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6WorkflowController => new CompetitionP6WorkflowController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CompetitionP6WorkflowService::class), ServiceReference::get($resolver, CompetitionResultCalculationService::class), ServiceReference::get($resolver, CompetitionScoreSheetService::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(CompetitionPublicResultsController::class, 'competition.results', [CompetitionP6RuntimeRepository::class,Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionPublicResultsController => new CompetitionPublicResultsController(ServiceReference::get($resolver, CompetitionP6RuntimeRepository::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP6VerifyConsoleCommand::class, 'competition.results', [DatabaseConnectionProvider::class, ScheduledTaskMap::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP6VerifyConsoleCommand => new CompetitionP6VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, ScheduledTaskMap::class)))));
        foreach (
            [
            ['competition.rounds.process', 'Open due rounds and close only complete scoring rounds.', 60],
            ['competition.score_sheets.remind', 'Create deduplicated reminders for nearing score-sheet deadlines.', 300],
            ['competition.appeal_windows.process', 'Open and close published-result appeal windows.', 60],
            ['competition.score_sheets.reconcile', 'Read-only score-sheet integrity reconciliation.', 900],
            ['competition.results.reconcile', 'Read-only result integrity reconciliation.', 900],
            ] as [$id, $description, $interval]
        ) {
            $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId($id), $description, new FixedIntervalSchedule($interval), CompetitionP6ScheduledMaintenanceTask::class, 120, 'competition.results'));
        }
    }
}
