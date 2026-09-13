<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionAppealAdjudication\Application\CompetitionAppealAdjudicationService;
use Qmdb\Modules\CompetitionAppealAdjudication\Application\CompetitionAppealMaintenanceScheduledTask;
use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence\CompetitionAppealMaintenanceService;
use Qmdb\Modules\CompetitionAppealAdjudication\Interface\Http\CompetitionAppealAdjudicationController;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
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

/** P7 review ownership; P6 remains the only base appeal aggregate. */
final readonly class CompetitionAppealAdjudicationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.appeal_adjudication');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.results'),
            new ModuleId('competition.result_publication'),
            new ModuleId('competition.scoring'),
            new ModuleId('competition.registration'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
            new ModuleId('identity.access'),
            new ModuleId('identity.multifactor'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(CompetitionAppealAdjudicationService::class, 'competition.appeal_adjudication', [DatabaseConnectionProvider::class, ContactCipher::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionAppealAdjudicationService => new CompetitionAppealAdjudicationService(ServiceReference::get($resolver, DatabaseConnectionProvider::class), ServiceReference::get($resolver, ContactCipher::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CompetitionAppealMaintenanceService::class, 'competition.appeal_adjudication', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionAppealMaintenanceService => new CompetitionAppealMaintenanceService(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(CompetitionAppealMaintenanceScheduledTask::class, 'competition.appeal_adjudication', [CompetitionAppealMaintenanceService::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionAppealMaintenanceScheduledTask => new CompetitionAppealMaintenanceScheduledTask(ServiceReference::get($resolver, CompetitionAppealMaintenanceService::class)))));
        $context->service(ServiceDefinition::factory(CompetitionAppealAdjudicationController::class, 'competition.appeal_adjudication', [AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class, IdentityCsrf::class, CompetitionAppealAdjudicationService::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionAppealAdjudicationController => new CompetitionAppealAdjudicationController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CompetitionAppealAdjudicationService::class), ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->scheduledTask(new ScheduledTaskRegistration(new ScheduledTaskId('competition.appeals.process'), 'Reconcile bounded P7 appeal decisions and correction authorizations.', new FixedIntervalSchedule(900), CompetitionAppealMaintenanceScheduledTask::class, 120, 'competition.appeal_adjudication'));
    }
}
