<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationRoleRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\EffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;

final readonly class DelegationValidator
{
    public function __construct(
        private EffectivePermissionRepository $effectivePermissions,
        private AuthorizationAdministrationRepository $administration,
    ) {
    }

    public function platform(
        int $actorAccountInternalId,
        AuthorizationRoleRecord $targetRole,
        bool $forUpdate = false,
    ): void {
        if ($targetRole->scopeType !== AuthorizationScopeType::PLATFORM || $targetRole->status !== RoleStatus::ACTIVE) {
            throw new AuthorizationDeniedException();
        }
        $this->assertSubset(
            $this->effectivePermissions->listEffectivePlatformPermissions($actorAccountInternalId, 100, $forUpdate),
            $this->administration->activePermissionsForRole($targetRole),
        );
    }

    public function workspace(
        AccountWorkspaceTenantContext $context,
        AuthorizationRoleRecord $targetRole,
        bool $forUpdate = false,
    ): void {
        if (
            $targetRole->scopeType !== AuthorizationScopeType::WORKSPACE
            || $targetRole->status !== RoleStatus::ACTIVE
        ) {
            throw new AuthorizationDeniedException();
        }
        $this->assertSubset(
            $this->effectivePermissions->listEffectiveWorkspacePermissions(
                $context,
                100,
                $forUpdate,
            ),
            $this->administration->activePermissionsForRole($targetRole),
        );
    }

    /**
     * @param list<\Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode> $actor
     * @param list<\Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode> $target
     */
    private function assertSubset(array $actor, array $target): void
    {
        $owned = [];
        foreach ($actor as $permission) {
            $owned[$permission->value()] = true;
        }
        foreach ($target as $permission) {
            if (!isset($owned[$permission->value()])) {
                throw new AuthorizationDeniedException();
            }
        }
    }
}
