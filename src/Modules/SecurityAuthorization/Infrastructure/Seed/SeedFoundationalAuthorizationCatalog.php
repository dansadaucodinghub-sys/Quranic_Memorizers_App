<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Seed;

use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedFoundationalAuthorizationCatalog implements Seed
{
    private AuthorizationCatalog $catalog;

    public function __construct(?AuthorizationCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? AuthorizationCatalogRegistry::foundational();
    }

    public function id(): SeedId
    {
        return new SeedId('20260826020100_seed_foundational_authorization_catalog');
    }

    public function description(): string
    {
        return 'Seed foundational authorization permission and system-role catalog.';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function steps(): array
    {
        $steps = [];
        $position = 1;
        foreach ($this->catalog->permissions() as $permission) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf(
                    '%03d_permission_%s',
                    $position++,
                    str_replace('.', '_', $permission->code->value()),
                )),
                'Insert the ' . $permission->code->value() . ' permission definition.',
                <<<'SQL'
INSERT INTO authorization_permissions
    (public_id, code, scope_type, required_assurance_level, status, owning_module, version,
        created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :code, :scope_type, :required_assurance, :status, :owning_module, :version,
        :created_at, :updated_at, :retired_at)
SQL,
                [
                    ':public_id' => $permission->id->toString(),
                    ':code' => $permission->code->value(),
                    ':scope_type' => $permission->scopeType->value,
                    ':required_assurance' => $permission->requiredAssurance->value,
                    ':status' => $permission->status->value,
                    ':owning_module' => $permission->owningModule,
                    ':version' => $permission->version,
                    ':created_at' => self::format($permission->createdAt),
                    ':updated_at' => self::format($permission->updatedAt),
                    ':retired_at' => $permission->retiredAt === null ? null : self::format($permission->retiredAt),
                ],
            );
        }
        foreach ($this->catalog->roles() as $role) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role_%s', $position++, str_replace('.', '_', $role->code->value()))),
                'Insert the ' . $role->code->value() . ' system-role definition.',
                <<<'SQL'
INSERT INTO authorization_roles
    (public_id, code, scope_type, status, is_system, version, created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :code, :scope_type, :status, :is_system, :version,
        :created_at, :updated_at, :retired_at)
SQL,
                [
                    ':public_id' => $role->id->toString(),
                    ':code' => $role->code->value(),
                    ':scope_type' => $role->scopeType->value,
                    ':status' => $role->status->value,
                    ':is_system' => $role->isSystem ? 1 : 0,
                    ':version' => $role->version,
                    ':created_at' => self::format($role->createdAt),
                    ':updated_at' => self::format($role->updatedAt),
                    ':retired_at' => $role->retiredAt === null ? null : self::format($role->retiredAt),
                ],
            );
        }
        $timestamp = self::format(new DateTimeImmutable('2026-08-28T00:00:00.000000Z'));
        foreach ($this->catalog->mappings() as $mapping) {
            $role = $this->catalog->role($mapping->roleCode);
            $permission = $this->catalog->permission($mapping->permissionCode);
            if ($role === null || $permission === null) {
                throw new \LogicException('Authorization seed mapping is inconsistent with its catalog.');
            }
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf(
                    '%03d_mapping_%s_%s',
                    $position++,
                    str_replace('.', '_', $mapping->roleCode->value()),
                    str_replace('.', '_', $mapping->permissionCode->value()),
                )),
                'Insert an explicit foundational role-permission mapping.',
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
                    ':created_at' => $timestamp,
                    ':permission_public_id' => $permission->id->toString(),
                    ':role_public_id' => $role->id->toString(),
                ],
            );
        }

        return $steps;
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
