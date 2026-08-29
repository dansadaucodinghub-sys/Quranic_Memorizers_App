<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogBuilder;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionId;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;

final readonly class PrivilegedAccessAuthorizationCatalog
{
    /** @var list<array{string, string, string, string}> */
    private const array PERMISSIONS = [
        ['01a0474d-a8f2-7001-9001-000000000001', 'platform.temporary_privileges.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7002-9002-000000000002', 'platform.temporary_privileges.request', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7003-9003-000000000003', 'platform.temporary_privileges.approve', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7004-9004-000000000004', 'platform.temporary_privileges.revoke', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7005-9005-000000000005', 'platform.support_access.request', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7006-9006-000000000006', 'platform.support_access.approve', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7007-9007-000000000007', 'platform.support_access.activate', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7008-9008-000000000008', 'platform.support_access.review', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7009-9009-000000000009', 'platform.break_glass.activate', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7010-9010-000000000010', 'platform.break_glass.review', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f2-7011-9011-000000000011', 'platform.break_glass.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7012-9012-000000000012', 'workspace.temporary_privileges.view', 'WORKSPACE', 'PRIMARY'],
        ['01a0474d-a8f2-7013-9013-000000000013', 'workspace.temporary_privileges.request', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7014-9014-000000000014', 'workspace.temporary_privileges.approve', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7015-9015-000000000015', 'workspace.temporary_privileges.revoke', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f2-7016-9016-000000000016', 'workspace.support_access.view', 'WORKSPACE', 'PRIMARY'],
        ['01a0474d-a8f2-7017-9017-000000000017', 'workspace.support_access.approve', 'WORKSPACE', 'MULTI_FACTOR'],
    ];

    /** @var list<array{string, string, string}> */
    private const array ROLES = [
        ['01a0474d-a8f2-7101-a101-000000000001', 'platform.privileged_access_administrator', 'PLATFORM'],
        ['01a0474d-a8f2-7102-a102-000000000002', 'platform.support_operator', 'PLATFORM'],
    ];

    /** @var array<string, list<string>> */
    private const array MAPPINGS = [
        'platform.privileged_access_administrator' => [
            'platform.temporary_privileges.view', 'platform.temporary_privileges.approve',
            'platform.temporary_privileges.revoke', 'platform.support_access.approve',
            'platform.support_access.review', 'platform.break_glass.view', 'platform.break_glass.review',
        ],
        'platform.support_operator' => [
            'platform.temporary_privileges.view', 'platform.temporary_privileges.request',
            'platform.support_access.request', 'platform.support_access.activate',
        ],
        'platform.security_administrator' => [
            'platform.temporary_privileges.view', 'platform.temporary_privileges.request',
            'platform.temporary_privileges.approve', 'platform.temporary_privileges.revoke',
            'platform.support_access.request', 'platform.support_access.approve', 'platform.support_access.activate',
            'platform.support_access.review', 'platform.break_glass.activate', 'platform.break_glass.review',
            'platform.break_glass.view',
        ],
        'platform.authorization_auditor' => [
            'platform.temporary_privileges.view', 'platform.support_access.review',
            'platform.break_glass.view', 'platform.break_glass.review',
        ],
        'workspace.owner' => [
            'workspace.temporary_privileges.view', 'workspace.temporary_privileges.request',
            'workspace.temporary_privileges.approve', 'workspace.temporary_privileges.revoke',
            'workspace.support_access.view', 'workspace.support_access.approve',
        ],
        'workspace.administrator' => [
            'workspace.temporary_privileges.view', 'workspace.temporary_privileges.request',
            'workspace.temporary_privileges.approve', 'workspace.temporary_privileges.revoke',
            'workspace.support_access.view', 'workspace.support_access.approve',
        ],
        'workspace.security_manager' => [
            'workspace.temporary_privileges.view', 'workspace.temporary_privileges.approve',
            'workspace.temporary_privileges.revoke', 'workspace.support_access.view',
            'workspace.support_access.approve',
        ],
        'workspace.membership_manager' => [
            'workspace.temporary_privileges.view', 'workspace.temporary_privileges.request',
            'workspace.support_access.view',
        ],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-08-29T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::from($scope),
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'security.privileged_access',
                1,
                $timestamp,
                $timestamp,
            ));
        }
        foreach (self::ROLES as [$id, $code, $scope]) {
            $builder->role(new RoleDefinition(
                RoleId::fromString($id),
                new RoleCode($code),
                AuthorizationScopeType::from($scope),
                RoleStatus::ACTIVE,
                true,
                1,
                $timestamp,
                $timestamp,
            ));
        }
        foreach (self::MAPPINGS as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }

    /** @return list<array{string, string, string, string}> */
    public static function permissions(): array
    {
        return self::PERMISSIONS;
    }

    /** @return list<array{string, string, string}> */
    public static function roles(): array
    {
        return self::ROLES;
    }

    /** @return array<string, list<string>> */
    public static function mappings(): array
    {
        return self::MAPPINGS;
    }
}
