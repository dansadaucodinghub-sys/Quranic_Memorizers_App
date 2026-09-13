<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Exact P7 workspace authorization definitions, mirrored by its applied seed. */
final readonly class CompetitionP7AuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a07000-0000-7001-8000-000000000001', 'workspace.competitions.operate_live', 'MULTI_FACTOR'],
        ['01a07000-0000-7001-8000-000000000002', 'workspace.competitions.rebuild_live_projection', 'PHISHING_RESISTANT'],
        ['01a07000-0000-7001-8000-000000000003', 'workspace.competitions.prepare_publications', 'MULTI_FACTOR'],
        ['01a07000-0000-7001-8000-000000000004', 'workspace.competitions.publish_provisional_results', 'PHISHING_RESISTANT'],
        ['01a07000-0000-7001-8000-000000000005', 'workspace.competitions.hold_publications', 'PHISHING_RESISTANT'],
        ['01a07000-0000-7001-8000-000000000006', 'workspace.competitions.finalize_publications', 'PHISHING_RESISTANT'],
        ['01a07000-0000-7001-8000-000000000007', 'workspace.competitions.adjudicate_appeals', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string}> */
    private const array ROLES = [
        ['01a07000-0000-7001-8000-000000000021', 'workspace.competition_live_operator'],
        ['01a07000-0000-7001-8000-000000000022', 'workspace.competition_publication_manager'],
        ['01a07000-0000-7001-8000-000000000023', 'workspace.competition_appeal_adjudicator'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-12T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'competition.live_operations', 1, $time, $time));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::WORKSPACE, RoleStatus::ACTIVE, true, 1, $time, $time));
        }
        $all = array_column(self::PERMISSIONS, 1);
        $mappings = [
            'workspace.owner' => $all,
            'workspace.administrator' => $all,
            'workspace.competition_manager' => ['workspace.competitions.operate_live', 'workspace.competitions.prepare_publications'],
            'workspace.competition_live_operator' => ['workspace.competitions.operate_live'],
            'workspace.competition_publication_manager' => ['workspace.competitions.prepare_publications', 'workspace.competitions.publish_provisional_results', 'workspace.competitions.hold_publications', 'workspace.competitions.finalize_publications'],
            'workspace.competition_appeal_adjudicator' => ['workspace.competitions.adjudicate_appeals', 'workspace.competitions.hold_publications'],
        ];
        foreach ($mappings as $role => $permissions) {
            foreach ($permissions as $code) {
                $builder->map(new RoleCode($role), new PermissionCode($code));
            }
        }
    }
}
