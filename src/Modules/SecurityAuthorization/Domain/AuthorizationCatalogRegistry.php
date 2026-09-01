<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessAuthorizationCatalog;

final readonly class AuthorizationCatalogRegistry
{
    private const array PERMISSIONS = [
        ['01a0474d-a8ee-745c-8ef7-a525b1d2f17c', 'platform.authorization.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f0-788e-bb03-043705d211b6', 'platform.authorization.assign', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01a0474d-a8f0-7037-96f3-8b038d114019', 'platform.security.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01a0474d-a8f0-723b-8053-dfafca1998d7', 'workspace.authorization.view', 'WORKSPACE', 'PRIMARY'],
        ['01a0474d-a8f0-7c64-ba7c-6f6814ba363a', 'workspace.authorization.assign', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f0-7849-9988-ca9bb4fe18f1', 'workspace.memberships.view', 'WORKSPACE', 'PRIMARY'],
        ['01a0474d-a8f0-73bd-9faf-305fce336fc1', 'workspace.memberships.manage', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f0-7988-96d8-9e25b9787d3b', 'workspace.security.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01a0474d-a8f0-7763-bc77-7ab9f42a20d7', 'workspace.settings.view', 'WORKSPACE', 'PRIMARY'],
        ['01a0474d-a8f0-7ef3-bc6d-9ed5be758b09', 'workspace.settings.manage', 'WORKSPACE', 'MULTI_FACTOR'],
    ];

    private const array ROLES = [
        ['01a0474d-a8f0-7e7b-b399-7a15c5dcbcd5', 'platform.security_administrator', 'PLATFORM'],
        ['01a0474d-a8f0-7178-97c8-4829dcc9d733', 'platform.authorization_auditor', 'PLATFORM'],
        ['01a0474d-a8f0-7e25-9b6b-578305dc4671', 'workspace.owner', 'WORKSPACE'],
        ['01a0474d-a8f0-78f9-81f5-0e229a95404e', 'workspace.administrator', 'WORKSPACE'],
        ['01a0474d-a8f0-75a7-8eb3-b9bb8f5a5cff', 'workspace.security_manager', 'WORKSPACE'],
        ['01a0474d-a8f1-7159-bc8b-c3f2f63a50d6', 'workspace.membership_manager', 'WORKSPACE'],
        ['01a0474d-a8f1-76c6-ad35-80b09e4d9694', 'workspace.viewer', 'WORKSPACE'],
    ];

    private const array MAPPINGS = [
        'platform.security_administrator' => [
            'platform.authorization.view', 'platform.authorization.assign', 'platform.security.view',
        ],
        'platform.authorization_auditor' => [
            'platform.authorization.view', 'platform.security.view',
        ],
        'workspace.owner' => [
            'workspace.authorization.view', 'workspace.authorization.assign', 'workspace.memberships.view',
            'workspace.memberships.manage', 'workspace.security.view', 'workspace.settings.view',
            'workspace.settings.manage',
        ],
        'workspace.administrator' => [
            'workspace.authorization.view', 'workspace.memberships.view', 'workspace.memberships.manage',
            'workspace.security.view', 'workspace.settings.view', 'workspace.settings.manage',
        ],
        'workspace.security_manager' => [
            'workspace.authorization.view', 'workspace.memberships.view', 'workspace.security.view',
        ],
        'workspace.membership_manager' => [
            'workspace.memberships.view', 'workspace.memberships.manage', 'workspace.settings.view',
        ],
        'workspace.viewer' => [
            'workspace.authorization.view', 'workspace.memberships.view', 'workspace.settings.view',
        ],
    ];

    public static function foundational(): AuthorizationCatalog
    {
        $builder = new AuthorizationCatalogBuilder();
        self::populateFoundational($builder);

        return $builder->build();
    }

    public static function withPrivilegedAccess(): AuthorizationCatalog
    {
        $builder = new AuthorizationCatalogBuilder();
        self::populateFoundational($builder);
        PrivilegedAccessAuthorizationCatalog::extend($builder);

        return $builder->build();
    }

    public static function withAuditAccountState(): AuthorizationCatalog
    {
        $builder = new AuthorizationCatalogBuilder();
        self::populateFoundational($builder);
        PrivilegedAccessAuthorizationCatalog::extend($builder);
        AccountStateAuthorizationCatalog::extend($builder);

        return $builder->build();
    }

    public static function withOrganizationsRegistry(): AuthorizationCatalog
    {
        $builder = new AuthorizationCatalogBuilder();
        self::populateFoundational($builder);
        PrivilegedAccessAuthorizationCatalog::extend($builder);
        AccountStateAuthorizationCatalog::extend($builder);
        OrganizationsAuthorizationCatalog::extend($builder);

        return $builder->build();
    }

    private static function populateFoundational(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-08-28T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::from($scope),
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'security.authorization',
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
}
