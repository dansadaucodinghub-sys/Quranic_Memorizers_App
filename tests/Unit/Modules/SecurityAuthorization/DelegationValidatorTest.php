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
use Qmdb\Modules\SecurityAuthorization\Application\DelegationValidator;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationRoleRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;
use Qmdb\Tests\Support\SecurityAuthorization\ConfigurableAuthorizationAdministrationRepository;
use Qmdb\Tests\Support\SecurityAuthorization\ConfigurableEffectivePermissionRepository;
use Qmdb\Tests\Support\TenancyContext\AccountWorkspaceTenantContextFactory;

final class DelegationValidatorTest extends TestCase
{
    public function testEqualAndSubsetPermissionSetsMayBeDelegated(): void
    {
        [$validator, $effective, $administration] = $this->validator();
        $effective->platformPermissions = [
            new PermissionCode('platform.authorization.view'),
            new PermissionCode('platform.authorization.assign'),
        ];
        $administration->rolePermissions = [new PermissionCode('platform.authorization.view')];
        $validator->platform(1, $this->role(AuthorizationScopeType::PLATFORM));
        self::addToAssertionCount(1);

        $administration->rolePermissions = $effective->platformPermissions;
        $validator->platform(1, $this->role(AuthorizationScopeType::PLATFORM));
        self::addToAssertionCount(1);
    }

    public function testMissingPermissionAndCrossScopeOrRetiredTargetAreDenied(): void
    {
        [$validator, $effective, $administration] = $this->validator();
        $effective->workspacePermissions = [new PermissionCode('workspace.authorization.view')];
        $administration->rolePermissions = [
            new PermissionCode('workspace.authorization.view'),
            new PermissionCode('workspace.authorization.assign'),
        ];
        $now = new DateTimeImmutable('2026-08-28T10:00:00Z');
        $context = AccountWorkspaceTenantContextFactory::create(new AuthenticatedAccountContext(
            1,
            AccountId::generate(),
            1,
            SessionId::generate(),
            1,
            DeviceId::generate(),
            $now,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                null,
                AuthenticationAssuranceLevel::PRIMARY,
                $now,
                null,
            ),
        ), 1);

        foreach (
            [
            $this->role(AuthorizationScopeType::WORKSPACE),
            $this->role(AuthorizationScopeType::PLATFORM),
            $this->role(AuthorizationScopeType::WORKSPACE, RoleStatus::RETIRED),
            ] as $index => $role
        ) {
            try {
                $validator->workspace($context, $role);
                self::fail('Delegation case ' . $index . ' must be denied.');
            } catch (AuthorizationDeniedException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return array{
     *   DelegationValidator,
     *   ConfigurableEffectivePermissionRepository,
     *   ConfigurableAuthorizationAdministrationRepository
     * }
     */
    private function validator(): array
    {
        $effective = new ConfigurableEffectivePermissionRepository();
        $administration = new ConfigurableAuthorizationAdministrationRepository();

        return [new DelegationValidator($effective, $administration), $effective, $administration];
    }

    private function role(
        AuthorizationScopeType $scope,
        RoleStatus $status = RoleStatus::ACTIVE,
    ): AuthorizationRoleRecord {
        return new AuthorizationRoleRecord(
            1,
            RoleId::generate(),
            new RoleCode($scope === AuthorizationScopeType::PLATFORM
                ? 'platform.security_administrator' : 'workspace.owner'),
            $scope,
            $status,
        );
    }
}
