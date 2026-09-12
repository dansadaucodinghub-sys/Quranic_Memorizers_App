<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedCompetitionP6AuthorizationRoleMappings implements Seed
{
    public function id(): SeedId { return new SeedId('20260911141100_seed_competition_p6_authorization_role_mappings'); }
    public function description(): string { return 'Seed exact P6 least-privilege role mappings without account assignments.'; }
    public function dependencies(): array { return [new SeedId('20260911141000_seed_competition_p6_authorization_catalog')]; }
    public function steps(): array
    {
        $roles = [
            '01a0474d-a8f0-7e25-9b6b-578305dc4671', '01a0474d-a8f0-78f9-81f5-0e229a95404e', '01a05000-0000-7001-8000-000000000021',
        ];
        $permissions = [
            '01a06000-0000-7001-8000-000000000001','01a06000-0000-7001-8000-000000000002','01a06000-0000-7001-8000-000000000003','01a06000-0000-7001-8000-000000000004','01a06000-0000-7001-8000-000000000005','01a06000-0000-7001-8000-000000000006','01a06000-0000-7001-8000-000000000007','01a06000-0000-7001-8000-000000000008',
        ];
        $steps = [];
        $sequence = 1;
        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $sequence++)), 'Map one exact P6 role permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)", [':role_id' => $role, ':permission_id' => $permission]);
            }
        }
        return $steps;
    }
}
