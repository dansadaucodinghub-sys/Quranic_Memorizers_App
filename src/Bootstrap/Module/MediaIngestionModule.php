<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\MediaIngestion\Application\MediaUploadWorkflow;
use Qmdb\Modules\MediaIngestion\Application\AuthorizedMediaUploadService;
use Qmdb\Modules\MediaIngestion\Infrastructure\Storage\LocalPrivateMediaBlobStore;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaCatalog\Domain\MediaLifecycle;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaIngestionModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.ingestion'); }
    public function dependencies(): array { return [new ModuleId('media.catalog'), new ModuleId('security.web'), new ModuleId('security.audit')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $root = getcwd() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'media-private';
        $context->service(ServiceDefinition::instance(LocalPrivateMediaBlobStore::class, 'media.ingestion', new LocalPrivateMediaBlobStore($root)));
        $context->alias(MediaBlobStore::class, LocalPrivateMediaBlobStore::class);
        $context->service(ServiceDefinition::factory(MediaUploadWorkflow::class, 'media.ingestion', [MediaEvidenceRepository::class, MediaBlobStore::class, MediaLifecycle::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): MediaUploadWorkflow => new MediaUploadWorkflow(ServiceReference::get($resolver, MediaEvidenceRepository::class), ServiceReference::get($resolver, MediaBlobStore::class), ServiceReference::get($resolver, MediaLifecycle::class)))));
        $context->service(ServiceDefinition::factory(AuthorizedMediaUploadService::class, 'media.ingestion', [MediaUploadWorkflow::class, MediaEvidenceRepository::class, \Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard::class, \Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter::class, \Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator::class, \Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender::class, \Qmdb\Shared\Database\Transaction\TransactionManager::class, \Qmdb\Shared\Time\Clock::class], new ClosureServiceFactory(static fn (DependencyResolver $resolver): AuthorizedMediaUploadService => new AuthorizedMediaUploadService(ServiceReference::get($resolver, MediaUploadWorkflow::class), ServiceReference::get($resolver, MediaEvidenceRepository::class), ServiceReference::get($resolver, \Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard::class), ServiceReference::get($resolver, \Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter::class), ServiceReference::get($resolver, \Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator::class), ServiceReference::get($resolver, \Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender::class), ServiceReference::get($resolver, \Qmdb\Shared\Database\Transaction\TransactionManager::class), ServiceReference::get($resolver, \Qmdb\Shared\Time\Clock::class)))));
    }
}
