<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Infrastructure\Seed;

use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedAccountStateAuthorizationCatalog implements Seed
{
    private AuthorizationCatalog $catalog;

    public function __construct(?AuthorizationCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? AuthorizationCatalogRegistry::withAuditAccountState();
    }

    public function id(): SeedId
    {
        return new SeedId('20260826020300_seed_account_state_authorization_catalog');
    }

    public function description(): string
    {
        return 'Extend authorization for audit viewing and account-state operations.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260826020200_seed_privileged_access_catalog')];
    }

    public function steps(): array
    {
        $timestamp = (new DateTimeImmutable('2026-08-29T00:00:00.000000Z'))->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
        $steps = [];
        $position = 1;
        $permissionCodes = [
            'platform.accounts.view', 'platform.accounts.suspend', 'platform.accounts.reactivate',
            'platform.security_events.view', 'platform.audit.verify',
        ];
        foreach ($permissionCodes as $code) {
            $permission = $this->catalog->permission(new PermissionCode($code));
            if ($permission === null) {
                throw new \LogicException('Account-state permission catalog is inconsistent.');
            }
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission', $position++)),
                'Insert a governed account-state permission definition.',
                <<<'SQL'
INSERT INTO authorization_permissions
    (public_id, code, scope_type, required_assurance_level, status, owning_module, version,
        created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :code, :scope_type, :required_assurance, :status, :owning_module, 1,
        :created_at, :updated_at, NULL)
SQL,
                [
                    ':public_id' => $permission->id->toString(), ':code' => $permission->code->value(),
                    ':scope_type' => $permission->scopeType->value,
                    ':required_assurance' => $permission->requiredAssurance->value,
                    ':status' => $permission->status->value, ':owning_module' => $permission->owningModule,
                    ':created_at' => $timestamp, ':updated_at' => $timestamp,
                ],
            );
        }
        foreach ($this->catalog->mappings() as $mapping) {
            if (!in_array($mapping->permissionCode->value(), $permissionCodes, true)) {
                continue;
            }
            $role = $this->catalog->role(new RoleCode($mapping->roleCode->value()));
            $permission = $this->catalog->permission(new PermissionCode($mapping->permissionCode->value()));
            if ($role === null || $permission === null) {
                throw new \LogicException('Account-state role mapping is inconsistent.');
            }
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_mapping', $position++)),
                'Insert a governed account-state role-permission mapping.',
                <<<'SQL'
INSERT INTO authorization_role_permissions
    (role_id, role_scope_type, permission_id, permission_scope_type, created_at)
SELECT role_definition.id, role_definition.scope_type, permission_definition.id, permission_definition.scope_type,
    :created_at
FROM authorization_roles role_definition
INNER JOIN authorization_permissions permission_definition ON permission_definition.public_id = UUID_TO_BIN(:permission_id)
WHERE role_definition.public_id = UUID_TO_BIN(:role_id)
SQL,
                [
                    ':created_at' => $timestamp, ':role_id' => $role->id->toString(),
                    ':permission_id' => $permission->id->toString(),
                ],
            );
        }

        return $steps;
    }
}
