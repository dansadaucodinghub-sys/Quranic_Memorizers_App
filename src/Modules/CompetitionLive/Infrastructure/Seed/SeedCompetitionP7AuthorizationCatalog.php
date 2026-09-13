<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Exact least-privilege P7 permissions.  P6 permission meanings remain untouched. */
final readonly class SeedCompetitionP7AuthorizationCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260912145000_seed_competition_p7_authorization_catalog');
    }

    public function description(): string
    {
        return 'Seed P7 live operations, result publication, and appeal adjudication authorization.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260912111000_correct_competition_p6_authorization_catalog')];
    }

    public function steps(): array
    {
        $permissions = [
            ['01a07000-0000-7001-8000-000000000001', 'workspace.competitions.operate_live', 'MULTI_FACTOR'],
            ['01a07000-0000-7001-8000-000000000002', 'workspace.competitions.rebuild_live_projection', 'PHISHING_RESISTANT'],
            ['01a07000-0000-7001-8000-000000000003', 'workspace.competitions.prepare_publications', 'MULTI_FACTOR'],
            ['01a07000-0000-7001-8000-000000000004', 'workspace.competitions.publish_provisional_results', 'PHISHING_RESISTANT'],
            ['01a07000-0000-7001-8000-000000000005', 'workspace.competitions.hold_publications', 'PHISHING_RESISTANT'],
            ['01a07000-0000-7001-8000-000000000006', 'workspace.competitions.finalize_publications', 'PHISHING_RESISTANT'],
            ['01a07000-0000-7001-8000-000000000007', 'workspace.competitions.adjudicate_appeals', 'PHISHING_RESISTANT'],
        ];
        $roles = [
            ['01a07000-0000-7001-8000-000000000021', 'workspace.competition_live_operator'],
            ['01a07000-0000-7001-8000-000000000022', 'workspace.competition_publication_manager'],
            ['01a07000-0000-7001-8000-000000000023', 'workspace.competition_appeal_adjudicator'],
        ];
        $mappings = [
            'workspace.owner' => array_column($permissions, 1),
            'workspace.administrator' => array_column($permissions, 1),
            'workspace.competition_manager' => ['workspace.competitions.operate_live', 'workspace.competitions.prepare_publications'],
            'workspace.competition_live_operator' => ['workspace.competitions.operate_live'],
            'workspace.competition_publication_manager' => ['workspace.competitions.prepare_publications', 'workspace.competitions.publish_provisional_results', 'workspace.competitions.hold_publications', 'workspace.competitions.finalize_publications'],
            'workspace.competition_appeal_adjudicator' => ['workspace.competitions.adjudicate_appeals', 'workspace.competitions.hold_publications'],
        ];
        $steps = [];
        foreach ($permissions as $position => [$id, $code, $assurance]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert one exact P7 workspace permission.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE',:assurance,'ACTIVE','competition.live_operations',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
        }
        foreach ($roles as $position => [$id, $code]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_role', $position + 8)), 'Insert one narrow P7 workspace role.', "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE','ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code]);
        }
        $sequence = 11;
        foreach ($mappings as $roleCode => $permissionCodes) {
            foreach ($permissionCodes as $permissionCode) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $sequence++)), 'Map one exact P7 role permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT role_definition.id,role_definition.scope_type,permission_definition.id,permission_definition.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles role_definition INNER JOIN authorization_permissions permission_definition ON permission_definition.code=:permission_code WHERE role_definition.code=:role_code", [':role_code' => $roleCode, ':permission_code' => $permissionCode]);
            }
        }

        return $steps;
    }
}
