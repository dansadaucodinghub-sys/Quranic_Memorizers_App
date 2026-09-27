<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final class ProductionHardeningP12AuthorizationCatalog
{
    /** @var list<array{string,string,string,string}> */
    public const array PERMISSIONS = [
        ['01999a12-7000-7000-8000-000000000001', 'account.notifications.view', 'PLATFORM', 'PRIMARY'],
        ['01999a12-7000-7000-8000-000000000002', 'account.notifications.update', 'PLATFORM', 'PRIMARY'],
        ['01999a12-7000-7000-8000-000000000003', 'account.privacy.request', 'PLATFORM', 'MULTI_FACTOR'],
        ['01999a12-7000-7000-8000-000000000004', 'workspace.integrations.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999a12-7000-7000-8000-000000000005', 'workspace.integrations.manage', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-000000000006', 'workspace.privacy.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999a12-7000-7000-8000-000000000007', 'workspace.privacy.manage', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-000000000008', 'platform.integrations.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-000000000009', 'platform.notifications.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-00000000000a', 'platform.privacy.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-00000000000b', 'platform.retention.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-00000000000d', 'platform.operations.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01999a12-7000-7000-8000-00000000000e', 'platform.operations.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-00000000000f', 'platform.incidents.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-000000000010', 'platform.backup.verify', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999a12-7000-7000-8000-000000000011', 'platform.keys.rotate', 'PLATFORM', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string,string}> */
    public const array ROLES = [
        ['01999a13-7000-7000-8000-000000000001', 'workspace.integration_manager', 'WORKSPACE'],
        ['01999a13-7000-7000-8000-000000000002', 'workspace.privacy_officer', 'WORKSPACE'],
        ['01999a13-7000-7000-8000-000000000003', 'platform.integration_operator', 'PLATFORM'],
        ['01999a13-7000-7000-8000-000000000004', 'platform.privacy_officer', 'PLATFORM'],
        ['01999a13-7000-7000-8000-000000000005', 'platform.security_incident_commander', 'PLATFORM'],
        ['01999a13-7000-7000-8000-000000000006', 'platform.recovery_operator', 'PLATFORM'],
    ];

    /** @return array<string,list<string>> */
    public static function mappings(): array
    {
        return [
            'workspace.integration_manager' => ['workspace.integrations.view', 'workspace.integrations.manage'],
            'workspace.privacy_officer' => ['workspace.privacy.view', 'workspace.privacy.manage'],
            'platform.integration_operator' => ['platform.integrations.manage', 'platform.notifications.manage', 'platform.operations.view'],
            'platform.privacy_officer' => ['platform.privacy.manage', 'platform.retention.manage', 'platform.audit.verify'],
            'platform.security_incident_commander' => ['platform.operations.view', 'platform.operations.manage', 'platform.incidents.manage', 'platform.audit.verify'],
            'platform.recovery_operator' => ['platform.operations.view', 'platform.backup.verify', 'platform.keys.rotate'],
        ];
    }

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-27T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::from($scope),
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'production.hardening',
                1,
                $time,
                $time,
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
                $time,
                $time,
            ));
        }
        foreach (self::mappings() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }
}
