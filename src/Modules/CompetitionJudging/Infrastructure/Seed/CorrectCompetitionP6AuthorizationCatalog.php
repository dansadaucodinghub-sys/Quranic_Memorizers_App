<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Forward-only correction of the initial P6 placeholder permissions. */
final readonly class CorrectCompetitionP6AuthorizationCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260912111000_correct_competition_p6_authorization_catalog');
    }

    public function description(): string
    {
        return 'Correct P6 least-privilege permissions and role mappings.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260911141100_seed_competition_p6_authorization_role_mappings')];
    }

    public function steps(): array
    {
        $permissions = [
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
        $roles = [
            ['01a06000-0000-7001-8000-000000000021', 'workspace.competition_judge'],
            ['01a06000-0000-7001-8000-000000000022', 'workspace.competition_head_judge'],
            ['01a06000-0000-7001-8000-000000000023', 'workspace.competition_result_manager'],
            ['01a06000-0000-7001-8000-000000000024', 'workspace.competition_appeal_reviewer'],
            ['01a06000-0000-7001-8000-000000000025', 'workspace.competition_result_auditor'],
        ];
        $mappings = [
            'workspace.owner' => array_column($permissions, 1),
            'workspace.administrator' => array_column($permissions, 1),
            'workspace.competition_manager' => ['workspace.competitions.manage_judges', 'workspace.competitions.manage_rounds', 'workspace.competitions.manage_panels', 'workspace.competitions.manage_rubrics', 'workspace.competitions.monitor_scoring', 'workspace.competitions.calculate_results'],
            'workspace.competition_judge' => ['workspace.competitions.judge_scores'],
            'workspace.competition_head_judge' => ['workspace.competitions.judge_scores', 'workspace.competitions.monitor_scoring'],
            'workspace.competition_result_manager' => ['workspace.competitions.monitor_scoring', 'workspace.competitions.correct_scores', 'workspace.competitions.calculate_results', 'workspace.competitions.view_sensitive_scores'],
            'workspace.competition_appeal_reviewer' => ['workspace.competitions.manage_appeals'],
            'workspace.competition_result_auditor' => ['workspace.competitions.view_sensitive_scores', 'workspace.competitions.audit_results'],
        ];

        $steps = [new SqlSeedStep(new SeedStepId('001_remove_prior_mappings'), 'Remove superseded P6 role mappings before rebuilding the exact set.', "DELETE mapping FROM authorization_role_permissions mapping INNER JOIN authorization_permissions permission_definition ON permission_definition.id = mapping.permission_id WHERE permission_definition.owning_module = 'competition.results'")];
        foreach ($permissions as $position => [$id, $code, $assurance]) {
            if ($position < 8) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_update_permission', $position + 2)), 'Correct one existing P6 permission definition.', "UPDATE authorization_permissions SET code=:code,required_assurance_level=:assurance,updated_at=UTC_TIMESTAMP(6) WHERE public_id=UUID_TO_BIN(:id)", [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
                continue;
            }
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_insert_permission', $position + 2)), 'Insert one missing P6 permission definition.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE',:assurance,'ACTIVE','competition.results',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
        }
        foreach ($roles as $position => [$id, $code]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_insert_role', $position + 16)), 'Insert one narrow P6 workspace role.', "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE','ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code]);
        }
        $sequence = 21;
        foreach ($mappings as $role => $permissionCodes) {
            foreach ($permissionCodes as $permissionCode) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $sequence++)), 'Map one exact P6 role permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT role_definition.id,role_definition.scope_type,permission_definition.id,permission_definition.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles role_definition INNER JOIN authorization_permissions permission_definition ON permission_definition.code=:permission_code WHERE role_definition.code=:role_code", [':role_code' => $role, ':permission_code' => $permissionCode]);
            }
        }

        return $steps;
    }
}
