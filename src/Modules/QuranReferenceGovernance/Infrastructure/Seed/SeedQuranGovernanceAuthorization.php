<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedQuranGovernanceAuthorization implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260910060200_seed_quran_governance_authorization');
    }
    public function description(): string
    {
        return 'Seed exact Platform Qur’an governance permissions and role mappings.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260910060100_seed_quran_reference_sources')];
    }
    public function steps(): array
    {
        $permissions = [
            ['01a0474d-c402-7001-8000-000000000001','platform.quran_sources.view','PRIMARY'],['01a0474d-c402-7001-8000-000000000002','platform.quran_releases.view','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000003','platform.quran_releases.manage','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000004','platform.quran_releases.approve','PHISHING_RESISTANT'],['01a0474d-c402-7001-8000-000000000005','platform.quran_releases.activate','PHISHING_RESISTANT'],
        ];
        $steps = [];
        foreach ($permissions as $i => [$id,$code,$assurance]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $i + 1)), 'Insert one exact Qur’an governance permission.', 'INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,\'PLATFORM\',:assurance,\'ACTIVE\',\'quran.reference_governance\',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':id' => $id,':code' => $code,':assurance' => $assurance]);
        }
        $steps[] = new SqlSeedStep(new SeedStepId('006_role'), 'Insert the narrow Qur’an governance platform role.', 'INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(\'01a0474d-c402-7001-8000-000000000006\'),\'platform.quran_governance_steward\',\'PLATFORM\',\'ACTIVE\',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)');
        $roleIds = ['01a0474d-a8f0-7e7b-b399-7a15c5dcbcd5','01a0474d-c402-7001-8000-000000000006'];
        $position = 7;
        foreach ($roleIds as $roleId) {
            foreach ($permissions as [$permissionId]) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position++)), 'Map a Platform governance role to a Qur’an permission.', 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => $roleId,':permission_id' => $permissionId]);
            }
        }
        foreach (array_slice($permissions, 0, 2) as [$permissionId]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_auditor_mapping', $position++)), 'Map the authorization auditor to view-only Qur’an permissions.', 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => '01a0474d-a8f0-7178-97c8-4829dcc9d733',':permission_id' => $permissionId]);
        }
        return $steps;
    }
}
