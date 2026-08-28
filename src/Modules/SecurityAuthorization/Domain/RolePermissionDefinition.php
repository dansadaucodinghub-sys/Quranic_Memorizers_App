<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

final readonly class RolePermissionDefinition
{
    public function __construct(
        public RoleCode $roleCode,
        public PermissionCode $permissionCode,
        public AuthorizationScopeType $scopeType,
    ) {
    }
}
