<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain\Repository;

use Qmdb\Modules\SecurityAuthorization\Domain\EffectivePermissionEvidence;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PersistedPermission;
use Qmdb\Modules\Tenancy\Application\TenantContext;

interface EffectivePermissionRepository
{
    public function permission(PermissionCode $code): ?PersistedPermission;

    public function accountIsActive(int $accountInternalId): bool;

    public function workspaceIsActive(TenantContext $context): bool;

    public function membershipIsActive(TenantContext $context, int $accountInternalId): bool;

    public function findEffectivePlatformPermission(
        int $accountInternalId,
        PermissionCode $permission,
    ): EffectivePermissionEvidence;

    public function findEffectiveWorkspacePermission(
        int $accountInternalId,
        TenantContext $context,
        PermissionCode $permission,
    ): EffectivePermissionEvidence;

    /** @return list<PermissionCode> */
    public function listEffectivePlatformPermissions(
        int $accountInternalId,
        int $limit = 100,
        bool $forUpdate = false,
    ): array;

    /** @return list<PermissionCode> */
    public function listEffectiveWorkspacePermissions(
        int $accountInternalId,
        TenantContext $context,
        int $limit = 100,
        bool $forUpdate = false,
    ): array;
}
