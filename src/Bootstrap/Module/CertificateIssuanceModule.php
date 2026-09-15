<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CertificateIssuance\Application\CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Application\CertificateSigningKeyProvider;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactRenderer;
use Qmdb\Modules\CertificateIssuance\Application\CertificateNumberAllocator;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactStore;
use Qmdb\Modules\CertificateIssuance\Application\CertificateIssuanceRepository;
use Qmdb\Modules\CertificateIssuance\Application\CertificateIssuanceService;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateLifecycle;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateManifestCanonicalizer;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Security\Ed25519CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Security\EnvironmentCertificateSigningKeyProvider;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact\LocalCertificateArtifactRenderer;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Artifact\LocalCertificateArtifactStore;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Persistence\MySqlCertificateIssuanceRepository;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Persistence\MySqlCertificateNumberAllocator;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateTemplateConfigurationValidator;
use Qmdb\Modules\CertificateIssuance\Interface\Console\CompetitionP8VerifyConsoleCommand;
use Qmdb\Modules\CertificateIssuance\Interface\Http\CertificateGovernanceController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CertificateIssuanceModule implements Module
{
    public function id(): ModuleId { return new ModuleId('certificate.issuance'); }
    public function dependencies(): array
    {
        return [new ModuleId('competition.result_publication'), new ModuleId('competition.results'), new ModuleId('people.profiles'), new ModuleId('identity.access'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('security.web'), new ModuleId('tenancy.context')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(CertificateManifestCanonicalizer::class, 'certificate.issuance', new CertificateManifestCanonicalizer()));
        $context->service(ServiceDefinition::instance(CertificateLifecycle::class, 'certificate.issuance', new CertificateLifecycle()));
        $context->service(ServiceDefinition::instance(CertificateTemplateConfigurationValidator::class, 'certificate.issuance', new CertificateTemplateConfigurationValidator()));
        $context->service(ServiceDefinition::instance(Ed25519CertificateSignatureVerifier::class, 'certificate.issuance', new Ed25519CertificateSignatureVerifier()));
        $context->alias(CertificateSignatureVerifier::class, Ed25519CertificateSignatureVerifier::class);
        $context->service(ServiceDefinition::instance(LocalCertificateArtifactRenderer::class, 'certificate.issuance', new LocalCertificateArtifactRenderer()));
        $context->alias(CertificateArtifactRenderer::class, LocalCertificateArtifactRenderer::class);
        $artifactRoot = getenv('QMDB_CERTIFICATE_ARTIFACT_ROOT');
        $context->service(ServiceDefinition::instance(LocalCertificateArtifactStore::class, 'certificate.issuance', new LocalCertificateArtifactStore(is_string($artifactRoot) && $artifactRoot !== '' ? $artifactRoot : getcwd() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'certificate-artifacts')));
        $context->alias(CertificateArtifactStore::class, LocalCertificateArtifactStore::class);
        $context->service(ServiceDefinition::factory(MySqlCertificateNumberAllocator::class, 'certificate.issuance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCertificateNumberAllocator => new MySqlCertificateNumberAllocator(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CertificateNumberAllocator::class, MySqlCertificateNumberAllocator::class);
        $context->service(ServiceDefinition::factory(MySqlCertificateIssuanceRepository::class, 'certificate.issuance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlCertificateIssuanceRepository => new MySqlCertificateIssuanceRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(CertificateIssuanceRepository::class, MySqlCertificateIssuanceRepository::class);
        $context->service(ServiceDefinition::factory(EnvironmentCertificateSigningKeyProvider::class, 'certificate.issuance', [ApplicationConfiguration::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): EnvironmentCertificateSigningKeyProvider => new EnvironmentCertificateSigningKeyProvider(ServiceReference::get($resolver, ApplicationConfiguration::class)->environment() === ApplicationEnvironment::PRODUCTION))));
        $context->alias(CertificateSigningKeyProvider::class, EnvironmentCertificateSigningKeyProvider::class);
        $context->service(ServiceDefinition::factory(CertificateIssuanceService::class, 'certificate.issuance', [CertificateIssuanceRepository::class, CertificateNumberAllocator::class, CertificateManifestCanonicalizer::class, CertificateLifecycle::class, CertificateSigningKeyProvider::class, CertificateArtifactRenderer::class, CertificateArtifactStore::class, AuthorizationRequirementGuard::class, StepUpGuard::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, ApplicationConfiguration::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CertificateIssuanceService => new CertificateIssuanceService(ServiceReference::get($resolver, CertificateIssuanceRepository::class), ServiceReference::get($resolver, CertificateNumberAllocator::class), ServiceReference::get($resolver, CertificateManifestCanonicalizer::class), ServiceReference::get($resolver, CertificateLifecycle::class), ServiceReference::get($resolver, CertificateSigningKeyProvider::class), ServiceReference::get($resolver, CertificateArtifactRenderer::class), ServiceReference::get($resolver, CertificateArtifactStore::class), ServiceReference::get($resolver, AuthorizationRequirementGuard::class), ServiceReference::get($resolver, StepUpGuard::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, ApplicationConfiguration::class), ServiceReference::get($resolver, SecurityAuditEventAppender::class), ServiceReference::get($resolver, TransactionManager::class), ServiceReference::get($resolver, Clock::class)))));
        $context->service(ServiceDefinition::factory(CertificateGovernanceController::class, 'certificate.issuance', [AuthenticatedRequestGuard::class, TenantContextRequiredGuard::class, IdentityCsrf::class, CertificateIssuanceService::class, IdentityAccessConfiguration::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CertificateGovernanceController => new CertificateGovernanceController(ServiceReference::get($resolver, AuthenticatedRequestGuard::class), ServiceReference::get($resolver, TenantContextRequiredGuard::class), ServiceReference::get($resolver, IdentityCsrf::class), ServiceReference::get($resolver, CertificateIssuanceService::class), ServiceReference::get($resolver, IdentityAccessConfiguration::class)->publicBaseUrl, ServiceReference::get($resolver, Psr17Factory::class)))));
        $context->service(ServiceDefinition::factory(CompetitionP8VerifyConsoleCommand::class, 'certificate.issuance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): CompetitionP8VerifyConsoleCommand => new CompetitionP8VerifyConsoleCommand(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
    }
}
