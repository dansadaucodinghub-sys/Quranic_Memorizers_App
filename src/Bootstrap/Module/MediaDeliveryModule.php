<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaDeliveryModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('media.delivery');
    }
    public function dependencies(): array
    {
        return [new ModuleId('media.moderation'), new ModuleId('media.ingestion'), new ModuleId('identity.sessions'), new ModuleId('tenancy.context'), new ModuleId('security.authorization'), new ModuleId('foundation.http')];
    }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(\Qmdb\Shared\DependencyInjection\ServiceDefinition::factory(\Qmdb\Modules\MediaDelivery\Interface\Http\PrivateMediaDeliveryController::class, 'media.delivery', [\Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard::class, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class, \Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard::class, \Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository::class, \Qmdb\Modules\MediaIngestion\Application\MediaBlobStore::class, \Nyholm\Psr7\Factory\Psr17Factory::class], new \Qmdb\Shared\DependencyInjection\ClosureServiceFactory(static fn (\Qmdb\Shared\DependencyInjection\DependencyResolver $resolver): \Qmdb\Modules\MediaDelivery\Interface\Http\PrivateMediaDeliveryController => new \Qmdb\Modules\MediaDelivery\Interface\Http\PrivateMediaDeliveryController(\Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard::class), \Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard::class), \Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard::class), \Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository::class), \Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Qmdb\Modules\MediaIngestion\Application\MediaBlobStore::class), \Qmdb\Shared\DependencyInjection\ServiceReference::get($resolver, \Nyholm\Psr7\Factory\Psr17Factory::class)))));
    }
}
