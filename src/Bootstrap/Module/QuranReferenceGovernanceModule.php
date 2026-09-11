<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

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
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranBaselineReleaseInstaller;
use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilUthmaniTextParser;
use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilQuranMetadataParser;
use Qmdb\Modules\QuranReferenceGovernance\Application\TanzilSimpleCleanTextParser;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranBaselineSearchCorpusInstaller;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranPublicReferenceRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchQueryNormalizer;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusIntegrityRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusValidationService;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence\MySqlQuranPublicReferenceRepository;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence\MySqlQuranSearchCorpusIntegrityRepository;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseManifest;
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
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranPublicReferenceController;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranSearchCorpusIntegrityController;
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
        $context->service(ServiceDefinition::factory(QuranB02ArtifactsVerifyConsoleCommand::class, 'quran.reference_governance', [], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranB02ArtifactsVerifyConsoleCommand($this->projectRoot))));
        $context->service(ServiceDefinition::instance(TanzilUthmaniTextParser::class, 'quran.reference_governance', new TanzilUthmaniTextParser()));
        $context->service(ServiceDefinition::instance(TanzilQuranMetadataParser::class, 'quran.reference_governance', new TanzilQuranMetadataParser()));
        $context->service(ServiceDefinition::instance(TanzilSimpleCleanTextParser::class, 'quran.reference_governance', new TanzilSimpleCleanTextParser()));
        $context->service(ServiceDefinition::factory(QuranBaselineSearchCorpusInstaller::class, 'quran.reference_governance', [DatabaseConnectionProvider::class, TanzilSimpleCleanTextParser::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranBaselineSearchCorpusInstaller($this->projectRoot, ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, TanzilSimpleCleanTextParser::class)))));
        $context->service(ServiceDefinition::instance(QuranSearchQueryNormalizer::class, 'quran.reference_governance', new QuranSearchQueryNormalizer()));
        $context->service(ServiceDefinition::factory(MySqlQuranSearchCorpusIntegrityRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranSearchCorpusIntegrityRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranSearchCorpusIntegrityRepository::class, MySqlQuranSearchCorpusIntegrityRepository::class);
        $context->service(ServiceDefinition::factory(MySqlQuranPublicReferenceRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranPublicReferenceRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranPublicReferenceRepository::class, MySqlQuranPublicReferenceRepository::class);
        $context->service(ServiceDefinition::instance(QuranReleaseManifest::class, 'quran.reference_governance', new QuranReleaseManifest()));
        $context->service(ServiceDefinition::factory(QuranBaselineReleaseInstaller::class, 'quran.reference_governance', [DatabaseConnectionProvider::class, TanzilUthmaniTextParser::class, TanzilQuranMetadataParser::class, QuranReleaseManifest::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranBaselineReleaseInstaller($this->projectRoot, ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, TanzilUthmaniTextParser::class), ServiceReference::get($r, TanzilQuranMetadataParser::class), ServiceReference::get($r, QuranReleaseManifest::class)))));
        $context->service(ServiceDefinition::factory(QuranReleaseImportConsoleCommand::class, 'quran.reference_governance', [QuranBaselineReleaseInstaller::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranReleaseImportConsoleCommand(ServiceReference::get($r, QuranBaselineReleaseInstaller::class)))));
        $context->service(ServiceDefinition::factory(QuranSearchCorpusImportConsoleCommand::class, 'quran.reference_governance', [QuranBaselineSearchCorpusInstaller::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSearchCorpusImportConsoleCommand(ServiceReference::get($r, QuranBaselineSearchCorpusInstaller::class)))));
        $context->service(ServiceDefinition::factory(QuranContentVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranContentVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(QuranSearchArtifactVerifyConsoleCommand::class, 'quran.reference_governance', [TanzilSimpleCleanTextParser::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSearchArtifactVerifyConsoleCommand($this->projectRoot, ServiceReference::get($r, TanzilSimpleCleanTextParser::class)))));
        $context->service(ServiceDefinition::factory(QuranSearchCorpusVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSearchCorpusVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(QuranPublicReferenceVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranPublicReferenceVerifyConsoleCommand($this->projectRoot, ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(QuranP4CloseoutVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranP4CloseoutVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::instance(QuranSourceArtifactPathGuard::class, 'quran.reference_governance', new QuranSourceArtifactPathGuard()));
        $context->service(ServiceDefinition::factory(QuranSourceArtifactRegisterConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class, QuranSourceArtifactPathGuard::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSourceArtifactRegisterConsoleCommand($this->projectRoot . '/resources/quran-source-artifacts', ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, QuranSourceArtifactPathGuard::class)))));
        $context->service(ServiceDefinition::instance(QuranReleaseLifecycle::class, 'quran.reference_governance', new QuranReleaseLifecycle()));
        $context->service(ServiceDefinition::factory(MySqlQuranReleaseLifecycleRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranReleaseLifecycleRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranReleaseLifecycleRepository::class, MySqlQuranReleaseLifecycleRepository::class);
        $context->service(ServiceDefinition::factory(MySqlQuranReleaseGovernanceReadRepository::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new MySqlQuranReleaseGovernanceReadRepository(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->alias(QuranReleaseGovernanceReadRepository::class, MySqlQuranReleaseGovernanceReadRepository::class);
        $context->service(ServiceDefinition::factory(QuranReleaseLifecycleService::class, 'quran.reference_governance', [QuranReleaseLifecycleRepository::class,QuranReleaseLifecycle::class,AuthorizationRequirementGuard::class,StepUpGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,SecurityAuditEventAppender::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranReleaseLifecycleService(ServiceReference::get($r, QuranReleaseLifecycleRepository::class), ServiceReference::get($r, QuranReleaseLifecycle::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, StepUpGuard::class), ServiceReference::get($r, IdentityRateLimiter::class), ServiceReference::get($r, IdentityFingerprintGenerator::class), ServiceReference::get($r, SecurityAuditEventAppender::class), ServiceReference::get($r, TransactionManager::class), ServiceReference::get($r, Clock::class)))));
        $context->service(ServiceDefinition::factory(QuranSearchCorpusValidationService::class, 'quran.reference_governance', [QuranSearchCorpusIntegrityRepository::class,AuthorizationRequirementGuard::class,StepUpGuard::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,SecurityAuditEventAppender::class,TransactionManager::class,Clock::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSearchCorpusValidationService(ServiceReference::get($r, QuranSearchCorpusIntegrityRepository::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, StepUpGuard::class), ServiceReference::get($r, IdentityRateLimiter::class), ServiceReference::get($r, IdentityFingerprintGenerator::class), ServiceReference::get($r, SecurityAuditEventAppender::class), ServiceReference::get($r, TransactionManager::class), ServiceReference::get($r, Clock::class)))));
        $context->service(ServiceDefinition::factory(QuranReleaseGovernanceController::class, 'quran.reference_governance', [AuthenticatedRequestGuard::class,AuthorizationRequirementGuard::class,IdentityCsrf::class,IdentityAccessView::class,Psr17Factory::class,QuranReleaseGovernanceReadRepository::class,QuranReleaseLifecycleService::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranReleaseGovernanceController(ServiceReference::get($r, AuthenticatedRequestGuard::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, IdentityCsrf::class), ServiceReference::get($r, IdentityAccessView::class), ServiceReference::get($r, Psr17Factory::class), ServiceReference::get($r, QuranReleaseGovernanceReadRepository::class), ServiceReference::get($r, QuranReleaseLifecycleService::class)))));
        $context->service(ServiceDefinition::factory(QuranPublicReferenceController::class, 'quran.reference_governance', [QuranPublicReferenceRepository::class,QuranSearchQueryNormalizer::class,IdentityRateLimiter::class,IdentityFingerprintGenerator::class,IdentityAccessView::class,Psr17Factory::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranPublicReferenceController(ServiceReference::get($r, QuranPublicReferenceRepository::class),ServiceReference::get($r,QuranSearchQueryNormalizer::class),ServiceReference::get($r,IdentityRateLimiter::class),ServiceReference::get($r,IdentityFingerprintGenerator::class),ServiceReference::get($r,IdentityAccessView::class),ServiceReference::get($r,Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(QuranSearchCorpusIntegrityController::class, 'quran.reference_governance', [AuthenticatedRequestGuard::class,AuthorizationRequirementGuard::class,IdentityCsrf::class,IdentityAccessView::class,Psr17Factory::class,QuranSearchCorpusIntegrityRepository::class,QuranSearchCorpusValidationService::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSearchCorpusIntegrityController(ServiceReference::get($r, AuthenticatedRequestGuard::class), ServiceReference::get($r, AuthorizationRequirementGuard::class), ServiceReference::get($r, IdentityCsrf::class), ServiceReference::get($r, IdentityAccessView::class), ServiceReference::get($r, Psr17Factory::class), ServiceReference::get($r, QuranSearchCorpusIntegrityRepository::class), ServiceReference::get($r, QuranSearchCorpusValidationService::class)))));
    }
}
