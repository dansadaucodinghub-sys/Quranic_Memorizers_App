<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\SecurityAuthorization;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;
use Qmdb\Modules\SecurityAuthorization\Domain\EffectivePermissionEvidence;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PersistedPermission;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\SecurityAuthorization\ConfigurableEffectivePermissionRepository;

final class AuthorizationDecisionTest extends TestCase
{
    public function testKnownActivePlatformAndWorkspacePermissionsAllowOnlyInTheirExactScope(): void
    {
        [$platformService, $platformRepository] = $this->serviceFor('platform.authorization.view');
        $platformDecision = $platformService->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::MULTI_FACTOR),
            new PermissionCode('platform.authorization.view'),
            new PlatformAuthorizationScope(),
        ));
        self::assertTrue($platformDecision->isAllowed());

        [$workspaceService, $workspaceRepository] = $this->serviceFor('workspace.authorization.view');
        $workspaceRepository->authorizedWorkspaceInternalId = 21;
        $allowed = $workspaceService->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::PRIMARY),
            new PermissionCode('workspace.authorization.view'),
            new WorkspaceAuthorizationScope($this->tenant(21)),
        ));
        $otherWorkspace = $workspaceService->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::PRIMARY),
            new PermissionCode('workspace.authorization.view'),
            new WorkspaceAuthorizationScope($this->tenant(22)),
        ));

        self::assertTrue($allowed->isAllowed());
        self::assertSame(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT, $otherWorkspace->reason);
        self::assertNotNull($platformRepository->persistedPermission);
    }

    public function testUnknownPermissionScopeMismatchAndPublicIdsAloneDeny(): void
    {
        [$service, $repository] = $this->serviceFor('platform.authorization.view');
        $unknown = $service->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::MULTI_FACTOR),
            new PermissionCode('platform.unregistered.permission'),
            new PlatformAuthorizationScope(),
        ));
        $scopeMismatch = $service->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::MULTI_FACTOR),
            new PermissionCode('platform.authorization.view'),
            new WorkspaceAuthorizationScope($this->tenant(30)),
        ));
        $repository->platformEvidence = new EffectivePermissionEvidence(false, false, false);
        $publicIdOnly = $service->decide(new AuthorizationRequest(
            $this->subject(AuthenticationAssuranceLevel::MULTI_FACTOR),
            new PermissionCode('platform.authorization.view'),
            new PlatformAuthorizationScope(),
        ));

        self::assertSame(AuthorizationDecisionReason::DENIED_UNKNOWN_PERMISSION, $unknown->reason);
        self::assertSame(AuthorizationDecisionReason::DENIED_SCOPE_MISMATCH, $scopeMismatch->reason);
        self::assertSame(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT, $publicIdOnly->reason);
    }

    public function testAccountWorkspaceMembershipAssignmentRolePermissionAndAssuranceMustAllRemainActive(): void
    {
        [$service, $repository] = $this->serviceFor('workspace.security.view');
        $request = fn (AuthenticationAssuranceLevel $level): AuthorizationRequest => new AuthorizationRequest(
            $this->subject($level),
            new PermissionCode('workspace.security.view'),
            new WorkspaceAuthorizationScope($this->tenant(41)),
        );

        $repository->activeAccount = false;
        self::assertSame(AuthorizationDecisionReason::DENIED_ACCOUNT_NOT_ACTIVE, $service->decide($request(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ))->reason);
        $repository->activeAccount = true;
        self::assertSame(AuthorizationDecisionReason::DENIED_INSUFFICIENT_ASSURANCE, $service->decide($request(
            AuthenticationAssuranceLevel::PRIMARY,
        ))->reason);
        $repository->activeWorkspace = false;
        self::assertSame(AuthorizationDecisionReason::DENIED_WORKSPACE_NOT_ACTIVE, $service->decide($request(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ))->reason);
        $repository->activeWorkspace = true;
        $repository->activeMembership = false;
        self::assertSame(AuthorizationDecisionReason::DENIED_MEMBERSHIP_NOT_ACTIVE, $service->decide($request(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ))->reason);
        $repository->activeMembership = true;
        $repository->workspaceEvidence = new EffectivePermissionEvidence(true, false, false);
        self::assertSame(AuthorizationDecisionReason::DENIED_ROLE_NOT_ACTIVE, $service->decide($request(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ))->reason);
        $repository->workspaceEvidence = new EffectivePermissionEvidence(true, true, false);
        self::assertSame(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT, $service->decide($request(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ))->reason);
        $repository->workspaceEvidence = new EffectivePermissionEvidence(true, true, true);
        self::assertTrue($service->decide($request(AuthenticationAssuranceLevel::MULTI_FACTOR))->isAllowed());
        self::assertTrue($service->decide($request(AuthenticationAssuranceLevel::PHISHING_RESISTANT))->isAllowed());
    }

    /** @return array{RoleBasedAuthorizationService, ConfigurableEffectivePermissionRepository} */
    private function serviceFor(string $permissionCode): array
    {
        $catalog = AuthorizationCatalogRegistry::foundational();
        $definition = $catalog->permission(new PermissionCode($permissionCode));
        self::assertNotNull($definition);
        $repository = new ConfigurableEffectivePermissionRepository();
        $repository->persistedPermission = new PersistedPermission(
            1,
            $definition->id,
            $definition->code,
            $definition->scopeType,
            $definition->requiredAssurance,
            $definition->status,
        );

        return [
            new RoleBasedAuthorizationService(
                $catalog,
                $repository,
                new AuthenticationAssuranceComparator(),
                new InMemoryEventLogger(),
            ),
            $repository,
        ];
    }

    private function subject(AuthenticationAssuranceLevel $assurance): AuthorizationSubject
    {
        $authenticatedAt = new DateTimeImmutable('2026-08-28T08:00:00Z');
        $strongAuthenticatedAt = $assurance === AuthenticationAssuranceLevel::PRIMARY ? null : $authenticatedAt;
        $secondary = match ($assurance) {
            AuthenticationAssuranceLevel::PRIMARY => null,
            AuthenticationAssuranceLevel::MULTI_FACTOR => AuthenticationMethod::TOTP,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT => AuthenticationMethod::PASSKEY,
        };

        return AuthorizationSubject::fromAuthenticatedContext(new AuthenticatedAccountContext(
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
                $secondary,
                $assurance,
                $authenticatedAt,
                $strongAuthenticatedAt,
            ),
        ));
    }

    private function tenant(int $internalId): TenantContext
    {
        return TenantContext::trusted($internalId, WorkspaceId::generate());
    }
}
