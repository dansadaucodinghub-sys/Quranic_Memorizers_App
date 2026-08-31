<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\SecurityHardening;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PersistedPermission;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Application\Cache\TenantScopedCacheKeyFactory;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\SecurityAuthorization\ConfigurableEffectivePermissionRepository;
use Qmdb\Tests\Support\TenancyContext\AccountWorkspaceTenantContextFactory;

#[Group('SecurityHardening')]
#[Group('SecurityPerformance')]
final class P2SecurityPerformanceBaselineTest extends TestCase
{
    public function testBoundedInMemorySecurityPrimitivesRemainPracticalAtOneThousandOperations(): void
    {
        $account = $this->authenticatedAccount();
        $context = AccountWorkspaceTenantContextFactory::create($account, 21);
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($account),
            new PermissionCode('workspace.authorization.view'),
            new WorkspaceAuthorizationScope($context),
        );
        $authorization = $this->authorizationService();
        $chain = new SecurityAuditHashChain();
        $cacheKeys = new TenantScopedCacheKeyFactory();
        $previousHash = SecurityAuditHashChain::GENESIS_HASH;

        $auditMilliseconds = $this->milliseconds(static function () use ($chain, &$previousHash): void {
            for ($sequence = 1; $sequence <= 1000; $sequence++) {
                $previousHash = $chain->eventHash(
                    ['event_code' => 'security.performance.baseline', 'sequence' => $sequence],
                    str_repeat("\x01", 32),
                    $previousHash,
                    'test-key',
                );
            }
        });
        $cacheMilliseconds = $this->milliseconds(static function () use ($cacheKeys, $context): void {
            for ($sequence = 1; $sequence <= 1000; $sequence++) {
                $cacheKeys->create($context, 'security.performance', 1, 'operation-' . $sequence);
            }
        });
        $authorizationMilliseconds = $this->milliseconds(static function () use ($authorization, $request): void {
            for ($sequence = 1; $sequence <= 1000; $sequence++) {
                $authorization->decide($request);
            }
        });

        self::assertNotSame(SecurityAuditHashChain::GENESIS_HASH, $previousHash);
        self::assertGreaterThan(0.0, $auditMilliseconds);
        self::assertGreaterThan(0.0, $cacheMilliseconds);
        self::assertGreaterThan(0.0, $authorizationMilliseconds);
        self::assertLessThan(5000.0, $auditMilliseconds);
        self::assertLessThan(5000.0, $cacheMilliseconds);
        self::assertLessThan(5000.0, $authorizationMilliseconds);
    }

    private function authorizationService(): RoleBasedAuthorizationService
    {
        $catalog = AuthorizationCatalogRegistry::foundational();
        $code = new PermissionCode('workspace.authorization.view');
        $definition = $catalog->permission($code);
        self::assertNotNull($definition);
        $repository = new ConfigurableEffectivePermissionRepository();
        $repository->authorizedWorkspaceInternalId = 21;
        $repository->persistedPermission = new PersistedPermission(
            1,
            $definition->id,
            $definition->code,
            $definition->scopeType,
            $definition->requiredAssurance,
            $definition->status,
        );

        return new RoleBasedAuthorizationService(
            $catalog,
            $repository,
            new AuthenticationAssuranceComparator(),
            new InMemoryEventLogger(),
        );
    }

    private function authenticatedAccount(): AuthenticatedAccountContext
    {
        $authenticatedAt = new DateTimeImmutable('2026-08-30T12:00:00.000000Z');

        return new AuthenticatedAccountContext(
            7,
            AccountId::generate(),
            11,
            SessionId::generate(),
            13,
            DeviceId::generate(),
            $authenticatedAt,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                AuthenticationMethod::TOTP,
                AuthenticationAssuranceLevel::MULTI_FACTOR,
                $authenticatedAt,
                $authenticatedAt,
            ),
        );
    }

    /** @param callable(): void $operation */
    private function milliseconds(callable $operation): float
    {
        $startedAt = hrtime(true);
        $operation();

        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
