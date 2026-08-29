<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\TenancyContext;

use DateTimeImmutable;
use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Application\Cache\TenantScopedCacheKeyFactory;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Application\TenantContextFreshnessValidator;
use Qmdb\Modules\TenancyContext\Application\TenantContextAttributes;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity;
use Qmdb\Modules\TenancyContext\Domain\SessionTenantContextState;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\TenancyContext\AccountWorkspaceTenantContextFactory;

final class TenantContextBoundaryTest extends TestCase
{
    public function testAccountWorkspaceContextIsExactImmutableAndNotSerializable(): void
    {
        $context = $this->tenant(4);

        self::assertSame(17, $context->accountInternalId);
        self::assertSame(41, $context->workspaceInternalId);
        self::assertSame(43, $context->membership->membershipInternalId);
        self::assertSame(4, $context->tenantContextVersion());
        self::assertSame($context->workspaceId->toString(), $context->tenant()->workspaceId()->toString());
        self::assertTrue((new \ReflectionClass($context))->getConstructor()?->isPrivate());
        self::assertTrue((new \ReflectionClass($context->membership))->getConstructor()?->isPrivate());
        $this->expectException(LogicException::class);
        serialize($context);
    }

    public function testTenantContextValuesRejectInvalidVersionsAndMismatchedMemberships(): void
    {
        try {
            new TenantContextVersion(0);
            self::fail('Tenant context version zero must be rejected.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        try {
            new TenantContextVersion(TenantContextVersion::MAX_VALUE + 1);
            self::fail('Tenant context versions beyond browser-safe integer range must be rejected.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $authenticated = $this->authenticated();
        foreach ([[42, $authenticated->accountInternalId], [41, 18]] as [$workspaceId, $accountId]) {
            try {
                AccountWorkspaceTenantContext::trusted(
                    $authenticated->accountInternalId,
                    $authenticated->accountId,
                    $authenticated->sessionInternalId,
                    $authenticated->sessionId,
                    41,
                    WorkspaceId::generate(),
                    WorkspaceStatus::ACTIVE,
                    1,
                    ResolvedWorkspaceMembershipIdentity::trusted(
                        43,
                        UuidV7::generate(),
                        $workspaceId,
                        $accountId,
                        MembershipStatus::ACTIVE,
                        1,
                    ),
                    'Mismatch',
                    new TenantContextVersion(1),
                    new DateTimeImmutable('2026-08-28T13:00:00+01:00'),
                );
                self::fail('Mismatched tenant membership identity must be rejected.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTenantContextEqualityUtcNormalizationAndDebugOutputAreSafe(): void
    {
        $authenticated = $this->authenticated();
        $workspaceId = WorkspaceId::generate();
        $membershipId = UuidV7::generate();
        $first = AccountWorkspaceTenantContextFactory::create(
            $authenticated,
            41,
            $workspaceId,
            43,
            $membershipId,
            4,
        );
        $same = AccountWorkspaceTenantContextFactory::create(
            $authenticated,
            41,
            $workspaceId,
            43,
            $membershipId,
            4,
        );
        self::assertTrue($first->equals($same));
        self::assertSame('+00:00', $first->selectedAt->format('P'));
        $debug = $first->__debugInfo();
        self::assertSame('[redacted]', $debug['account_id']);
        self::assertSame('[redacted]', $debug['session_id']);
        self::assertArrayNotHasKey('workspace_name', $debug);
        self::assertArrayNotHasKey('membership_public_id', $debug);
        self::assertArrayNotHasKey('membership_public_id', $first->membership->__debugInfo());
        foreach (['token', 'email', 'phone', 'role', 'permission'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower((string)json_encode($debug)));
        }

        $this->expectException(LogicException::class);
        serialize($first->membership);
    }

    public function testSessionTenantContextStateIsExactRedactedAndNotSerializable(): void
    {
        $context = $this->tenant(4);
        $state = new SessionTenantContextState(
            $context->sessionInternalId,
            $context->accountInternalId,
            $context->workspaceInternalId,
            $context->membershipInternalId(),
            $context->version,
            $context->selectedAt,
            SessionStatus::ACTIVE,
            2,
            true,
            $context,
        );
        self::assertSame($context->sessionInternalId, $state->sessionInternalId);
        self::assertSame($context->accountInternalId, $state->accountInternalId);
        self::assertSame('[redacted]', $state->__debugInfo()['session_id']);
        self::assertArrayNotHasKey('workspace_internal_id', $state->__debugInfo());

        $this->expectException(LogicException::class);
        serialize($state);
    }

    public function testTenantGuardRequiresServerAttributeAndFreshMutationVersion(): void
    {
        $guard = new TenantContextRequiredGuard(new TenantContextFreshnessValidator());
        $context = $this->tenant(6);
        $request = (new ServerRequest('POST', '/workspace'))
            ->withAttribute(TenantContextAttributes::CONTEXT, $context)
            ->withHeader('X-QMDB-Tenant-Context-Version', '6');
        self::assertSame($context, $guard->require($request, true));
        $maximum = $this->tenant(TenantContextVersion::MAX_VALUE);
        self::assertSame($maximum, $guard->require(
            $request->withAttribute(TenantContextAttributes::CONTEXT, $maximum)
                ->withHeader('X-QMDB-Tenant-Context-Version', (string)TenantContextVersion::MAX_VALUE),
            true,
        ));

        try {
            $guard->require(
                $request->withAttribute(TenantContextAttributes::CONTEXT, $maximum)
                    ->withHeader('X-QMDB-Tenant-Context-Version', '9999999999999999'),
                true,
            );
            self::fail('An out-of-range browser version must be treated as stale, not as an unhandled input error.');
        } catch (StaleTenantContextException) {
            self::addToAssertionCount(1);
        }

        try {
            $guard->require(new ServerRequest('GET', '/workspace'));
            self::fail('Missing tenant context must fail closed.');
        } catch (TenantContextRequiredException $exception) {
            self::assertSame('TENANT_CONTEXT_REQUIRED', $exception->safeCode());
        }

        $this->expectException(StaleTenantContextException::class);
        $guard->require($request->withHeader('X-QMDB-Tenant-Context-Version', '5'), true);
    }

    public function testFreshnessValidatorAcceptsExactAndOptionalVersionsAndRejectsMissingOrFutureVersions(): void
    {
        $validator = new TenantContextFreshnessValidator();
        $authoritative = new TenantContextVersion(6);
        self::assertTrue($validator->validate($authoritative, new TenantContextVersion(6), true)->isFresh());
        $optional = $validator->validate($authoritative, null, false);
        self::assertFalse($optional->clientVersionProvided);

        foreach ([null, new TenantContextVersion(7)] as $provided) {
            try {
                $validator->validate($authoritative, $provided, true);
                self::fail('Missing or future required tenant context version must be stale.');
            } catch (StaleTenantContextException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTenantCacheKeysAreWorkspaceVersionAndNamespaceSeparated(): void
    {
        $factory = new TenantScopedCacheKeyFactory();
        $workspaceId = WorkspaceId::generate();
        $authenticated = $this->authenticated();
        $firstContext = AccountWorkspaceTenantContextFactory::create($authenticated, 41, $workspaceId, 43);
        $sameContext = AccountWorkspaceTenantContextFactory::create($authenticated, 41, $workspaceId, 43);
        $first = $factory->create($firstContext, 'memorizer.list', 1, 'page=1')->value();
        $same = $factory->create($sameContext, 'memorizer.list', 1, 'page=1')->value();
        $second = $factory->create($this->tenant(3), 'memorizer.list', 1, 'page=1')->value();
        $other = $factory->create($firstContext, 'memorizer.detail', 1, 'page=1')->value();

        self::assertSame($first, $same);
        self::assertNotSame($first, $second);
        self::assertNotSame($first, $other);
        self::assertStringContainsString($workspaceId->toString(), $first);
        self::assertStringNotContainsString(':41:', $first);
        self::assertStringNotContainsString('page=1', $first);
    }

    public function testTenantCacheKeyRejectsUnsafeNamespaceAndNonPositiveSchemaVersion(): void
    {
        $factory = new TenantScopedCacheKeyFactory();
        foreach ([['unsafe namespace', 1], ['safe.namespace', 0]] as [$namespace, $version]) {
            try {
                $factory->create($this->tenant(1), $namespace, $version, 'bounded');
                self::fail('Invalid cache-key input must be rejected.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSafeProblemDetailsPreserveTenantFailureCodesWithoutDetails(): void
    {
        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Workspace Context Changed',
            'status' => 409,
            'code' => 'TENANT_CONTEXT_STALE',
        ], ProblemDetails::safe(409, 'TENANT_CONTEXT_STALE')->toArray());
    }

    private function tenant(int $version): AccountWorkspaceTenantContext
    {
        $authenticated = $this->authenticated();

        return AccountWorkspaceTenantContextFactory::create($authenticated, 41, tenantContextVersion: $version);
    }

    private function authenticated(): AuthenticatedAccountContext
    {
        $now = new DateTimeImmutable('2026-08-28T12:00:00Z');

        return new AuthenticatedAccountContext(
            17,
            AccountId::generate(),
            19,
            SessionId::generate(),
            23,
            DeviceId::generate(),
            $now,
            2,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                null,
                AuthenticationAssuranceLevel::PRIMARY,
                $now,
                null,
            ),
        );
    }
}
