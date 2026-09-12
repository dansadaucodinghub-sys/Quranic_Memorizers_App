<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Exact P6 workspace permissions; existing P5 permission meanings are untouched. */
final readonly class CompetitionP6AuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a06000-0000-7001-8000-000000000001', 'workspace.competitions.manage_judges', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000002', 'workspace.competitions.manage_rounds', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000003', 'workspace.competitions.manage_rubrics', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000004', 'workspace.competitions.judge_scores', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000005', 'workspace.competitions.calculate_results', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000006', 'workspace.competitions.verify_results', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000007', 'workspace.competitions.publish_results', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000008', 'workspace.competitions.manage_appeals', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000009', 'workspace.competitions.manage_panels', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000010', 'workspace.competitions.monitor_scoring', 'PRIMARY'],
        ['01a06000-0000-7001-8000-000000000011', 'workspace.competitions.correct_scores', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000012', 'workspace.competitions.disqualify_participants', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000013', 'workspace.competitions.view_sensitive_scores', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000014', 'workspace.competitions.audit_results', 'MULTI_FACTOR'],
    ];

    /** @var list<array{string,string}> */
    private const array ROLES = [
        ['01a06000-0000-7001-8000-000000000021', 'workspace.competition_judge'],
        ['01a06000-0000-7001-8000-000000000022', 'workspace.competition_head_judge'],
        ['01a06000-0000-7001-8000-000000000023', 'workspace.competition_result_manager'],
        ['01a06000-0000-7001-8000-000000000024', 'workspace.competition_appeal_reviewer'],
        ['01a06000-0000-7001-8000-000000000025', 'workspace.competition_result_auditor'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-11T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'competition.results', 1, $time, $time));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::WORKSPACE, RoleStatus::ACTIVE, true, 1, $time, $time));
        }
        $all = array_column(self::PERMISSIONS, 1);
        $mappings = [
            'workspace.owner' => $all,
            'workspace.administrator' => $all,
            'workspace.competition_manager' => ['workspace.competitions.manage_judges', 'workspace.competitions.manage_rounds', 'workspace.competitions.manage_panels', 'workspace.competitions.manage_rubrics', 'workspace.competitions.monitor_scoring', 'workspace.competitions.calculate_results'],
            'workspace.competition_judge' => ['workspace.competitions.judge_scores'],
            'workspace.competition_head_judge' => ['workspace.competitions.judge_scores', 'workspace.competitions.monitor_scoring'],
            'workspace.competition_result_manager' => ['workspace.competitions.monitor_scoring', 'workspace.competitions.correct_scores', 'workspace.competitions.calculate_results', 'workspace.competitions.view_sensitive_scores'],
            'workspace.competition_appeal_reviewer' => ['workspace.competitions.manage_appeals'],
            'workspace.competition_result_auditor' => ['workspace.competitions.view_sensitive_scores', 'workspace.competitions.audit_results'],
        ];
        foreach ($mappings as $role => $permissions) {
            foreach ($permissions as $code) {
                $builder->map(new RoleCode($role), new PermissionCode($code));
            }
        }
    }
}
