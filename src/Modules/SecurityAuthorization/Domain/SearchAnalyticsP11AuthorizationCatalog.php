<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Closed P11 authority catalog; no role assignment is seeded. */
final class SearchAnalyticsP11AuthorizationCatalog
{
    /** @var list<array{string,string,string,string}> */
    public const array PERMISSIONS = [
        ['01998f62-7000-7000-8000-000000000001', 'workspace.search.view', 'WORKSPACE', 'PRIMARY'],
        ['01998f62-7000-7000-8000-000000000002', 'workspace.analytics.view', 'WORKSPACE', 'PRIMARY'],
        ['01998f62-7000-7000-8000-000000000003', 'workspace.analytics.view_sensitive', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01998f62-7000-7000-8000-000000000004', 'workspace.reports.view', 'WORKSPACE', 'PRIMARY'],
        ['01998f62-7000-7000-8000-000000000005', 'workspace.reports.run', 'WORKSPACE', 'MULTI_FACTOR'],
        ['01998f62-7000-7000-8000-000000000006', 'workspace.reports.export', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01998f62-7000-7000-8000-000000000007', 'workspace.reports.approve_sensitive', 'WORKSPACE', 'PHISHING_RESISTANT'],
        ['01998f62-7000-7000-8000-000000000008', 'platform.search.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01998f62-7000-7000-8000-000000000009', 'platform.analytics.view', 'PLATFORM', 'MULTI_FACTOR'],
        ['01998f62-7000-7000-8000-00000000000a', 'platform.analytics.view_sensitive', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01998f62-7000-7000-8000-00000000000b', 'platform.reports.run_national', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01998f62-7000-7000-8000-00000000000c', 'platform.reports.approve_sensitive', 'PLATFORM', 'PHISHING_RESISTANT'],
        ['01998f62-7000-7000-8000-00000000000d', 'platform.p11.audit', 'PLATFORM', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string,string}> */
    public const array ROLES = [
        ['01998f63-7000-7000-8000-000000000001', 'workspace.analytics_viewer', 'WORKSPACE'],
        ['01998f63-7000-7000-8000-000000000002', 'workspace.report_operator', 'WORKSPACE'],
        ['01998f63-7000-7000-8000-000000000003', 'workspace.report_approver', 'WORKSPACE'],
        ['01998f63-7000-7000-8000-000000000004', 'platform.analytics_analyst', 'PLATFORM'],
        ['01998f63-7000-7000-8000-000000000005', 'platform.analytics_auditor', 'PLATFORM'],
    ];

    /** @return array<string,list<string>> */
    public static function mappings(): array
    {
        return [
            'workspace.analytics_viewer' => ['workspace.search.view', 'workspace.analytics.view', 'workspace.reports.view'],
            'workspace.report_operator' => ['workspace.search.view', 'workspace.analytics.view', 'workspace.reports.view', 'workspace.reports.run', 'workspace.reports.export'],
            'workspace.report_approver' => ['workspace.reports.view', 'workspace.reports.approve_sensitive'],
            'platform.analytics_analyst' => ['platform.search.view', 'platform.analytics.view', 'platform.reports.run_national'],
            'platform.analytics_auditor' => ['platform.search.view', 'platform.analytics.view', 'platform.analytics.view_sensitive', 'platform.reports.approve_sensitive', 'platform.p11.audit'],
        ];
    }

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-25T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $scope, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::from($scope),
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'search.analytics_reporting',
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
