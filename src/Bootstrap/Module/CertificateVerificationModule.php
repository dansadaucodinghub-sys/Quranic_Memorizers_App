<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\CertificateIssuance\Application\CertificateSignatureVerifier;
use Qmdb\Modules\CertificateIssuance\Application\CertificateArtifactStore;
use Qmdb\Modules\CertificateVerification\Application\PublicCertificateVerificationReader;
use Qmdb\Modules\CertificateVerification\Infrastructure\Persistence\MySqlPublicCertificateVerificationReader;
use Qmdb\Modules\CertificateVerification\Interface\Http\PublicCertificateVerificationController;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CertificateVerificationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('certificate.verification');
    }
    public function dependencies(): array
    {
        return [new ModuleId('certificate.issuance'), new ModuleId('foundation.database'), new ModuleId('foundation.http'), new ModuleId('security.web')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(MySqlPublicCertificateVerificationReader::class, 'certificate.verification', [DatabaseConnectionProvider::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlPublicCertificateVerificationReader => new MySqlPublicCertificateVerificationReader(ServiceReference::get($resolver, DatabaseConnectionProvider::class)))));
        $context->alias(PublicCertificateVerificationReader::class, MySqlPublicCertificateVerificationReader::class);
        $context->service(ServiceDefinition::factory(PublicCertificateVerificationController::class, 'certificate.verification', [PublicCertificateVerificationReader::class, CertificateSignatureVerifier::class, CertificateArtifactStore::class, IdentityRateLimiter::class, IdentityFingerprintGenerator::class, Psr17Factory::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): PublicCertificateVerificationController => new PublicCertificateVerificationController(ServiceReference::get($resolver, PublicCertificateVerificationReader::class), ServiceReference::get($resolver, CertificateSignatureVerifier::class), ServiceReference::get($resolver, CertificateArtifactStore::class), ServiceReference::get($resolver, IdentityRateLimiter::class), ServiceReference::get($resolver, IdentityFingerprintGenerator::class), ServiceReference::get($resolver, Psr17Factory::class)))));
    }
}
