<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final class PilotOfflineRolloutP13AuthorizationCatalog
{
    /** @var list<array{string,string,string,string}> */
    public const array PERMISSIONS = [
        ['01999b01-7000-7000-8000-000000000001', 'workspace.offline_devices.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-000000000002', 'workspace.offline_devices.manage', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000003', 'workspace.offline_devices.activate', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000004', 'workspace.offline_devices.revoke', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000005', 'workspace.offline_packages.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-000000000006', 'workspace.offline_packages.prepare', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000007', 'workspace.offline_packages.download', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-000000000008', 'workspace.offline_packages.revoke', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000009', 'workspace.offline_sync.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-00000000000a', 'workspace.offline_conflicts.view', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-00000000000b', 'workspace.offline_conflicts.resolve', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-00000000000c', 'platform.pilots.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-00000000000d', 'platform.pilots.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-00000000000e', 'platform.pilots.approve', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-00000000000f', 'platform.rollouts.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01999b01-7000-7000-8000-000000000010', 'platform.rollouts.manage', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000011', 'platform.rollouts.decide', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000012', 'platform.rollouts.activate', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000013', 'platform.rollouts.pause', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000014', 'platform.rollouts.contain', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01999b01-7000-7000-8000-000000000015', 'platform.rollouts.audit', 'PLATFORM', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string,string}> */
    public const array ROLES = [
        ['01999b02-7000-7000-8000-000000000001', 'workspace.offline_venue_operator', 'WORKSPACE'],
        ['01999b02-7000-7000-8000-000000000002', 'workspace.offline_device_manager', 'WORKSPACE'],
        ['01999b02-7000-7000-8000-000000000003', 'workspace.offline_conflict_reviewer', 'WORKSPACE'],
        ['01999b02-7000-7000-8000-000000000004', 'platform.pilot_manager', 'PLATFORM'],
        ['01999b02-7000-7000-8000-000000000005', 'platform.rollout_manager', 'PLATFORM'],
        ['01999b02-7000-7000-8000-000000000006', 'platform.rollout_approver', 'PLATFORM'],
        ['01999b02-7000-7000-8000-000000000007', 'platform.rollout_auditor', 'PLATFORM'],
    ];

    /** @return array<string, list<string>> */
    public static function mappings(): array
    {
        return [
            'workspace.offline_venue_operator' => ['workspace.offline_devices.view', 'workspace.offline_packages.view', 'workspace.offline_packages.download', 'workspace.offline_sync.view'],
            'workspace.offline_device_manager' => ['workspace.offline_devices.view', 'workspace.offline_devices.manage', 'workspace.offline_devices.activate', 'workspace.offline_devices.revoke', 'workspace.offline_packages.view', 'workspace.offline_packages.prepare', 'workspace.offline_packages.revoke'],
            'workspace.offline_conflict_reviewer' => ['workspace.offline_sync.view', 'workspace.offline_conflicts.view', 'workspace.offline_conflicts.resolve'],
            'platform.pilot_manager' => ['platform.pilots.view', 'platform.pilots.manage'],
            'platform.rollout_manager' => ['platform.rollouts.view', 'platform.rollouts.manage', 'platform.rollouts.pause'],
            'platform.rollout_approver' => ['platform.pilots.view', 'platform.pilots.approve', 'platform.rollouts.view', 'platform.rollouts.decide', 'platform.rollouts.activate', 'platform.rollouts.pause', 'platform.rollouts.contain'],
            'platform.rollout_auditor' => ['platform.pilots.view', 'platform.rollouts.view', 'platform.rollouts.audit'],
        ];
    }

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-28T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::from($scope),
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'pilot.offline_rollout',
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
