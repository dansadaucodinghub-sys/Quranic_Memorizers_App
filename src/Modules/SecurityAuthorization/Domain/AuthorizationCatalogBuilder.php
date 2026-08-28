<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use LogicException;

final class AuthorizationCatalogBuilder
{
    /** @var array<string, PermissionDefinition> */
    private array $permissions = [];

    /** @var array<string, RoleDefinition> */
    private array $roles = [];

    /** @var array<string, bool> */
    private array $publicIds = [];

    /** @var list<RolePermissionDefinition> */
    private array $mappings = [];

    /** @var array<string, bool> */
    private array $mappingKeys = [];

    private bool $frozen = false;

    public function permission(PermissionDefinition $permission): self
    {
        $this->assertOpen();
        $code = $permission->code->value();
        if (isset($this->permissions[$code])) {
            throw new LogicException('Duplicate permission code.');
        }
        $this->assertPublicId($permission->id->toString());
        $this->permissions[$code] = $permission;

        return $this;
    }

    public function role(RoleDefinition $role): self
    {
        $this->assertOpen();
        $code = $role->code->value();
        if (isset($this->roles[$code])) {
            throw new LogicException('Duplicate role code.');
        }
        $this->assertPublicId($role->id->toString());
        $this->roles[$code] = $role;

        return $this;
    }

    public function map(RoleCode $roleCode, PermissionCode $permissionCode): self
    {
        $this->assertOpen();
        $role = $this->roles[$roleCode->value()] ?? null;
        $permission = $this->permissions[$permissionCode->value()] ?? null;
        if ($role === null || $permission === null) {
            throw new LogicException('Role-permission mapping references an unregistered definition.');
        }
        if ($role->scopeType !== $permission->scopeType) {
            throw new LogicException('Cross-scope role-permission mapping is prohibited.');
        }
        $key = $roleCode->value() . "\0" . $permissionCode->value();
        if (isset($this->mappingKeys[$key])) {
            throw new LogicException('Duplicate role-permission mapping.');
        }
        $this->mappingKeys[$key] = true;
        $this->mappings[] = new RolePermissionDefinition($roleCode, $permissionCode, $role->scopeType);

        return $this;
    }

    public function build(): AuthorizationCatalog
    {
        $this->assertOpen();
        $this->frozen = true;
        ksort($this->permissions, SORT_STRING);
        ksort($this->roles, SORT_STRING);
        usort(
            $this->mappings,
            static fn (RolePermissionDefinition $left, RolePermissionDefinition $right): int =>
                [$left->roleCode->value(), $left->permissionCode->value()]
                <=> [$right->roleCode->value(), $right->permissionCode->value()],
        );

        return new AuthorizationCatalog(
            array_values($this->permissions),
            array_values($this->roles),
            $this->mappings,
        );
    }

    private function assertPublicId(string $publicId): void
    {
        if (isset($this->publicIds[$publicId])) {
            throw new LogicException('Duplicate authorization public ID.');
        }
        $this->publicIds[$publicId] = true;
    }

    private function assertOpen(): void
    {
        if ($this->frozen) {
            throw new LogicException('Authorization catalog builder is frozen.');
        }
    }
}
