<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\P4DecompositionVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranGovernanceVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourcesVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourceArtifactRegisterConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranSourceArtifactPathGuard;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseLifecycle;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseLifecycleRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseLifecycleService;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseGovernanceReadRepository;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence\MySqlQuranReleaseGovernanceReadRepository;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence\MySqlQuranReleaseLifecycleRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranReleaseGovernanceController;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class QuranReferenceGovernanceModule implements Module
{
    public function __construct(private string $projectRoot)
    {
    }
    public function id(): ModuleId
    {
        return new ModuleId('quran.reference_governance');
    }
    public function dependencies(): array
    {
        return [new ModuleId('foundation.core'),new ModuleId('foundation.application'),new ModuleId('foundation.observability'),new ModuleId('foundation.database'),new ModuleId('foundation.schema'),new ModuleId('foundation.http'),new ModuleId('foundation.presentation'),new ModuleId('security.web'),new ModuleId('security.authorization'),new ModuleId('security.audit'),new ModuleId('identity.accounts'),new ModuleId('identity.access'),new ModuleId('identity.sessions'),new ModuleId('identity.multifactor')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(P4DecompositionVerifyConsoleCommand::class, 'quran.reference_governance', [], new ClosureServiceFactory(fn(DependencyResolver $r) => new P4DecompositionVerifyConsoleCommand($this->projectRoot))));
        $context->service(ServiceDefinition::factory(QuranSourcesVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSourcesVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(QuranGovernanceVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranGovernanceVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::instance(QuranSourceArtifactPathGuard::class, 'quran.reference_governance', new QuranSourceArtifactPathGuard()));
        $context->service(ServiceDefinition::factory(QuranSourceArtifactRegisterConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class, QuranSourceArtifactPathGuard::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSourceArtifactRegisterConsoleCommand($this->projectRoot . '/resources/quran-source-artifacts', ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, QuranSourceArtifactPathGuard::class)))));
        $context->service(ServiceDefinition::instance(QuranReleaseLifecycle::class, 'quran.reference_governance', new QuranReleaseLifecycle()));
        $context->service(ServiceDefinition::factory(MySqlQuranReleaseLifecycleRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranReleaseLifecycleRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranReleaseLifecycleRepository::class, MySqlQuranReleaseLifecycleRepository::class);
        $context->service(ServiceDefinition::factory(MySqlQuranReleaseGovernanceReadRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranReleaseGovernanceReadRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranReleaseGovernanceReadRepository::class, MySqlQuranReleaseGovernanceReadRepository::class);
        $context->service(ServiceDefinition::factory(QuranReleaseLifecycleService::class, 'quran.reference_governance', [QuranReleaseLifecycleRepository::class,QuranReleaseLifecycle::class,AuthorizationRequirementGuard::class,StepUpGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,SecurityAuditEventAppender::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranReleaseLifecycleService(ServiceReference::get($r, QuranReleaseLifecycleRepository::class), ServiceReference::get($r, QuranReleaseLifecycle::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, StepUpGuard::class), ServiceReference::get($r, IdentityRateLimiter::class), ServiceReference::get($r, IdentityFingerprintGenerator::class), ServiceReference::get($r, SecurityAuditEventAppender::class), ServiceReference::get($r, TransactionManager::class), ServiceReference::get($r, Clock::class)))));
        $context->service(ServiceDefinition::factory(QuranReleaseGovernanceController::class, 'quran.reference_governance', [AuthenticatedRequestGuard::class,AuthorizationRequirementGuard::class,IdentityCsrf::class,IdentityAccessView::class,Psr17Factory::class,QuranReleaseGovernanceReadRepository::class,QuranReleaseLifecycleService::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranReleaseGovernanceController(ServiceReference::get($r, AuthenticatedRequestGuard::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, IdentityCsrf::class), ServiceReference::get($r, IdentityAccessView::class), ServiceReference::get($r, Psr17Factory::class), ServiceReference::get($r, QuranReleaseGovernanceReadRepository::class), ServiceReference::get($r, QuranReleaseLifecycleService::class)))));
    }
}
