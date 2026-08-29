<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;

final readonly class PrivilegedAccessPermissionPolicyCatalog
{
    /** @return list<array{PrivilegedAccessType, string, AuthorizationScopeType}> */
    public static function entries(): array
    {
        return [
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'platform.authorization.view', AuthorizationScopeType::PLATFORM],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'platform.security.view', AuthorizationScopeType::PLATFORM],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.authorization.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.memberships.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.memberships.manage', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.security.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.settings.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::TEMPORARY_PRIVILEGE, 'workspace.settings.manage', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::SUPPORT_ACCESS, 'workspace.authorization.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::SUPPORT_ACCESS, 'workspace.memberships.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::SUPPORT_ACCESS, 'workspace.security.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::SUPPORT_ACCESS, 'workspace.settings.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'platform.authorization.view', AuthorizationScopeType::PLATFORM],
            [PrivilegedAccessType::BREAK_GLASS, 'platform.security.view', AuthorizationScopeType::PLATFORM],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.authorization.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.memberships.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.memberships.manage', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.security.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.settings.view', AuthorizationScopeType::WORKSPACE],
            [PrivilegedAccessType::BREAK_GLASS, 'workspace.settings.manage', AuthorizationScopeType::WORKSPACE],
        ];
    }

    public static function permits(
        PrivilegedAccessType $accessType,
        string $permissionCode,
        AuthorizationScopeType $scope,
    ): bool {
        foreach (self::entries() as [$type, $code, $entryScope]) {
            if ($type === $accessType && $code === $permissionCode && $entryScope === $scope) {
                return true;
            }
        }

        return false;
    }
}
