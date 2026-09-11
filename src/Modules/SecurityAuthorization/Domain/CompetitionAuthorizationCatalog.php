<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** P5 adds exact workspace capability definitions without changing a prior permission meaning. */
final readonly class CompetitionAuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a05000-0000-7001-8000-000000000001', 'workspace.competitions.view', 'PRIMARY'],
        ['01a05000-0000-7001-8000-000000000002', 'workspace.competitions.manage_programs', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000003', 'workspace.competitions.manage_editions', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000004', 'workspace.competitions.manage_organizers', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000005', 'workspace.competitions.manage_venues', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000006', 'workspace.competitions.manage_categories', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000007', 'workspace.competitions.manage_eligibility', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000008', 'workspace.competitions.manage_registration_windows', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000009', 'workspace.competitions.review_registrations', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000010', 'workspace.competitions.view_sensitive_registration', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000011', 'workspace.competitions.manage_capacity', 'MULTI_FACTOR'],
        ['01a05000-0000-7001-8000-000000000012', 'workspace.competitions.finalize_rosters', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string}> */
    private const array ROLES = [
        ['01a05000-0000-7001-8000-000000000021', 'workspace.competition_manager'],
        ['01a05000-0000-7001-8000-000000000022', 'workspace.competition_registrar'],
        ['01a05000-0000-7001-8000-000000000023', 'workspace.competition_reviewer'],
        ['01a05000-0000-7001-8000-000000000024', 'workspace.competition_viewer'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-09-11T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'competition.configuration', 1, $timestamp, $timestamp));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::WORKSPACE, RoleStatus::ACTIVE, true, 1, $timestamp, $timestamp));
        }
        $all = array_map(static fn (array $entry): string => $entry[1], self::PERMISSIONS);
        $maps = [
            'workspace.owner' => $all,
            'workspace.administrator' => $all,
            'workspace.competition_manager' => $all,
            'workspace.competition_registrar' => [$all[0], $all[1], $all[8], $all[9], $all[10]],
            'workspace.competition_reviewer' => [$all[0], $all[8], $all[9]],
            'workspace.competition_viewer' => [$all[0]],
        ];
        foreach ($maps as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }
}
