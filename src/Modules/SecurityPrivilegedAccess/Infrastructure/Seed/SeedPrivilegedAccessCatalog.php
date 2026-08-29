<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Seed;

use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleDefinition;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessAuthorizationCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessPermissionPolicyCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessPermissionPolicyStatus;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedPrivilegedAccessCatalog implements Seed
{
    private AuthorizationCatalog $catalog;

    public function __construct(?AuthorizationCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? AuthorizationCatalogRegistry::withPrivilegedAccess();
    }

    public function id(): SeedId
    {
        return new SeedId('20260826020200_seed_privileged_access_catalog');
    }

    public function description(): string
    {
        return 'Seed the fixed privileged-access authorization catalog and permission policies.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260826020100_seed_foundational_authorization_catalog')];
    }

    public function steps(): array
    {
        $timestamp = self::format(new DateTimeImmutable('2026-08-29T00:00:00.000000Z'));
        $steps = [];
        $position = 1;

        foreach (PrivilegedAccessAuthorizationCatalog::permissions() as [, $code]) {
            $permission = $this->requiredPermission($code);
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission_%s', $position++, str_replace('.', '_', $code))),
                'Insert the ' . $code . ' permission definition.',
                <<<'SQL'
INSERT INTO authorization_permissions
    (public_id, code, scope_type, required_assurance_level, status, owning_module, version,
        created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :code, :scope_type, :required_assurance, :status, :owning_module, :version,
        :created_at, :updated_at, NULL)
SQL,
                [
                    ':public_id' => $permission->id->toString(), ':code' => $permission->code->value(),
                    ':scope_type' => $permission->scopeType->value,
                    ':required_assurance' => $permission->requiredAssurance->value,
                    ':status' => $permission->status->value, ':owning_module' => $permission->owningModule,
                    ':version' => $permission->version, ':created_at' => $timestamp, ':updated_at' => $timestamp,
                ],
            );
        }
        foreach (PrivilegedAccessAuthorizationCatalog::roles() as [, $code]) {
            $role = $this->requiredRole($code);
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role_%s', $position++, str_replace('.', '_', $code))),
                'Insert the ' . $code . ' system role.',
                <<<'SQL'
INSERT INTO authorization_roles
    (public_id, code, scope_type, status, is_system, version, created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :code, :scope_type, :status, :is_system, :version, :created_at, :updated_at, NULL)
SQL,
                [
                    ':public_id' => $role->id->toString(), ':code' => $role->code->value(),
                    ':scope_type' => $role->scopeType->value, ':status' => $role->status->value,
                    ':is_system' => $role->isSystem ? 1 : 0, ':version' => $role->version,
                    ':created_at' => $timestamp, ':updated_at' => $timestamp,
                ],
            );
        }
        foreach (PrivilegedAccessAuthorizationCatalog::mappings() as $roleCode => $permissionCodes) {
            $role = $this->requiredRole($roleCode);
            foreach ($permissionCodes as $permissionCode) {
                $permission = $this->requiredPermission($permissionCode);
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_mapping', $position++)),
                    'Insert an explicit privileged-access role-permission mapping.',
                    <<<'SQL'
INSERT INTO authorization_role_permissions
    (role_id, role_scope_type, permission_id, permission_scope_type, created_at)
SELECT role_definition.id, role_definition.scope_type,
    permission_definition.id, permission_definition.scope_type, :created_at
FROM authorization_roles role_definition
INNER JOIN authorization_permissions permission_definition
    ON permission_definition.public_id = UUID_TO_BIN(:permission_public_id)
WHERE role_definition.public_id = UUID_TO_BIN(:role_public_id)
SQL,
                    [
                        ':created_at' => $timestamp, ':role_public_id' => $role->id->toString(),
                        ':permission_public_id' => $permission->id->toString(),
                    ],
                );
            }
        }
        foreach (PrivilegedAccessPermissionPolicyCatalog::entries() as [$accessType, $permissionCode, $scope]) {
            $permission = $this->requiredPermission($permissionCode);
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_policy', $position++)),
                'Insert a fixed privileged-access permission policy.',
                <<<'SQL'
INSERT INTO privileged_access_permission_policies
    (access_type, permission_id, permission_scope_type, status, created_at, updated_at, retired_at)
SELECT :access_type, definition.id, :scope_type, :status, :created_at, :updated_at, NULL
FROM authorization_permissions definition
WHERE definition.public_id = UUID_TO_BIN(:permission_public_id)
SQL,
                [
                    ':access_type' => $accessType->value, ':permission_public_id' => $permission->id->toString(),
                    ':scope_type' => $scope->value, ':status' => PrivilegedAccessPermissionPolicyStatus::ACTIVE->value,
                    ':created_at' => $timestamp, ':updated_at' => $timestamp,
                ],
            );
        }

        return $steps;
    }

    private function requiredPermission(string $code): PermissionDefinition
    {
        return $this->catalog->permission(new PermissionCode($code))
            ?? throw new \LogicException('Privileged-access permission catalog is inconsistent.');
    }

    private function requiredRole(string $code): RoleDefinition
    {
        return $this->catalog->role(new RoleCode($code))
            ?? throw new \LogicException('Privileged-access role catalog is inconsistent.');
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
