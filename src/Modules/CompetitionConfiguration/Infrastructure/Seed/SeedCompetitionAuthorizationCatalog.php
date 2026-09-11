<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Seeds capabilities only; it intentionally creates no account-to-role assignment. */
final readonly class SeedCompetitionAuthorizationCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260911131000_seed_competition_authorization_catalog');
    }
    public function description(): string
    {
        return 'Seed exact workspace-scoped P5 competition permissions, roles, and least-privilege mappings.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260911120000_correct_quran_metadata_source_reference')];
    }

    public function steps(): array
    {
        $permissions = [
            ['01a05000-0000-7001-8000-000000000001','workspace.competitions.view','PRIMARY'],
            ['01a05000-0000-7001-8000-000000000002','workspace.competitions.manage_programs','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000003','workspace.competitions.manage_editions','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000004','workspace.competitions.manage_organizers','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000005','workspace.competitions.manage_venues','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000006','workspace.competitions.manage_categories','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000007','workspace.competitions.manage_eligibility','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000008','workspace.competitions.manage_registration_windows','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000009','workspace.competitions.review_registrations','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000010','workspace.competitions.view_sensitive_registration','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000011','workspace.competitions.manage_capacity','MULTI_FACTOR'],
            ['01a05000-0000-7001-8000-000000000012','workspace.competitions.finalize_rosters','PHISHING_RESISTANT'],
        ];
        $roles = [
            ['01a05000-0000-7001-8000-000000000021','workspace.competition_manager'],
            ['01a05000-0000-7001-8000-000000000022','workspace.competition_registrar'],
            ['01a05000-0000-7001-8000-000000000023','workspace.competition_reviewer'],
            ['01a05000-0000-7001-8000-000000000024','workspace.competition_viewer'],
        ];
        $steps = [];
        foreach ($permissions as $position => [$id, $code, $assurance]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert one exact P5 workspace permission.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE',:assurance,'ACTIVE','competition.configuration',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':assurance' => $assurance]);
        }
        foreach ($roles as $position => [$id, $code]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_role', $position + 13)), 'Insert one narrow P5 workspace role.', "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE','ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code]);
        }
        $maps = [
            '01a05000-0000-7001-8000-000000000021' => array_column($permissions, 0),
            '01a05000-0000-7001-8000-000000000022' => array_slice(array_column($permissions, 0), 0, 2) + [8 => $permissions[8][0], 9 => $permissions[9][0], 10 => $permissions[10][0]],
            '01a05000-0000-7001-8000-000000000023' => [$permissions[0][0], $permissions[8][0], $permissions[9][0]],
            '01a05000-0000-7001-8000-000000000024' => [$permissions[0][0]],
        ];
        $sequence = 17;
        foreach ($maps as $roleId => $permissionIds) {
            foreach ($permissionIds as $permissionId) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $sequence++)), 'Map an exact P5 workspace role to an exact permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)", [':role_id' => $roleId, ':permission_id' => $permissionId]);
            }
        }
        return $steps;
    }
}
