<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieFactory;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Modules\SecurityWeb\Csrf\HmacCsrfTokenManager;
use Qmdb\Modules\SecurityWeb\Csrf\SameOriginMutationValidator;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;

final readonly class SecurityWebModule implements Module
{
    private const ID = 'security.web';

    public function __construct(private IdentityAccessConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.core')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            HmacCsrfTokenManager::class,
            self::ID,
            [SecretsProvider::class],
            new ClosureServiceFactory(function (DependencyResolver $resolver): HmacCsrfTokenManager {
                $key = ServiceReference::get($resolver, SecretsProvider::class)
                    ->get(SecretName::fromString('AUTH_CSRF_SIGNING_KEY'));

                return new HmacCsrfTokenManager($key->reveal(), $this->configuration->csrfTtlSeconds);
            }),
        ));
        $context->alias(CsrfTokenManager::class, HmacCsrfTokenManager::class);
        $context->service(ServiceDefinition::factory(
            CsrfCookieFactory::class,
            self::ID,
            [],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): CsrfCookieFactory => new CsrfCookieFactory(
                $this->configuration->productionLike,
                $this->configuration->csrfTtlSeconds,
            )),
        ));
        $context->service(ServiceDefinition::factory(
            SameOriginMutationValidator::class,
            self::ID,
            [CsrfTokenManager::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): SameOriginMutationValidator =>
                new SameOriginMutationValidator(
                    ServiceReference::get($resolver, CsrfTokenManager::class),
                    $this->configuration->publicBaseUrl->origin(),
                )),
        ));
    }
}
