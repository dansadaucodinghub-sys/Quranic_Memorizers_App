<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\SecurityAuthorization;

use Qmdb\Modules\SecurityAuthorization\Domain\EffectivePermissionEvidence;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PersistedPermission;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\EffectivePermissionRepository;
use Qmdb\Modules\Tenancy\Application\TenantContext;

final class ConfigurableEffectivePermissionRepository implements EffectivePermissionRepository
{
    public ?PersistedPermission $persistedPermission = null;
    public bool $activeAccount = true;
    public bool $activeWorkspace = true;
    public bool $activeMembership = true;
    public EffectivePermissionEvidence $platformEvidence;
    public EffectivePermissionEvidence $workspaceEvidence;
    public ?int $authorizedWorkspaceInternalId = null;

    /** @var list<PermissionCode> */
    public array $platformPermissions = [];

    /** @var list<PermissionCode> */
    public array $workspacePermissions = [];

    public function __construct()
    {
        $this->platformEvidence = new EffectivePermissionEvidence(true, true, true);
        $this->workspaceEvidence = new EffectivePermissionEvidence(true, true, true);
    }

    public function permission(PermissionCode $code): ?PersistedPermission
    {
        return $this->persistedPermission?->code->equals($code) === true
            ? $this->persistedPermission
            : null;
    }

    public function accountIsActive(int $accountInternalId): bool
    {
        return $this->activeAccount;
    }

    public function workspaceIsActive(TenantContext $context): bool
    {
        return $this->activeWorkspace;
    }

    public function membershipIsActive(TenantContext $context, int $accountInternalId): bool
    {
        return $this->activeMembership;
    }

    public function findEffectivePlatformPermission(
        int $accountInternalId,
        PermissionCode $permission,
    ): EffectivePermissionEvidence {
        return $this->platformEvidence;
    }

    public function findEffectiveWorkspacePermission(
        int $accountInternalId,
        TenantContext $context,
        PermissionCode $permission,
    ): EffectivePermissionEvidence {
        if (
            $this->authorizedWorkspaceInternalId !== null
            && $context->workspaceInternalId() !== $this->authorizedWorkspaceInternalId
        ) {
            return new EffectivePermissionEvidence(false, false, false);
        }

        return $this->workspaceEvidence;
    }

    public function listEffectivePlatformPermissions(
        int $accountInternalId,
        int $limit = 100,
        bool $forUpdate = false,
    ): array {
        return array_slice($this->platformPermissions, 0, $limit);
    }

    public function listEffectiveWorkspacePermissions(
        int $accountInternalId,
        TenantContext $context,
        int $limit = 100,
        bool $forUpdate = false,
    ): array {
        return array_slice($this->workspacePermissions, 0, $limit);
    }
}
