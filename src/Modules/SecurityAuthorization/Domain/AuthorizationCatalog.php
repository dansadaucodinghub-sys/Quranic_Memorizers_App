<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

final readonly class AuthorizationCatalog
{
    /**
     * @param list<PermissionDefinition> $permissions
     * @param list<RoleDefinition> $roles
     * @param list<RolePermissionDefinition> $mappings
     */
    public function __construct(
        private array $permissions,
        private array $roles,
        private array $mappings,
    ) {
    }

    /** @return list<PermissionDefinition> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** @return list<RoleDefinition> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return list<RolePermissionDefinition> */
    public function mappings(): array
    {
        return $this->mappings;
    }

    public function permission(PermissionCode $code): ?PermissionDefinition
    {
        foreach ($this->permissions as $permission) {
            if ($permission->code->equals($code)) {
                return $permission;
            }
        }

        return null;
    }

    public function role(RoleCode $code): ?RoleDefinition
    {
        foreach ($this->roles as $role) {
            if ($role->code->equals($code)) {
                return $role;
            }
        }

        return null;
    }

    /** @return list<PermissionCode> */
    public function permissionsForRole(RoleCode $roleCode): array
    {
        $permissions = [];
        foreach ($this->mappings as $mapping) {
            if ($mapping->roleCode->equals($roleCode)) {
                $permissions[] = $mapping->permissionCode;
            }
        }

        return $permissions;
    }
}
