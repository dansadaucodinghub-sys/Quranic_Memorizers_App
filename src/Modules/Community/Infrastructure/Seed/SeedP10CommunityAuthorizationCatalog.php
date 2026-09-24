<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\CommunityP10AuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedP10CommunityAuthorizationCatalog implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260922102000_seed_p10_community_authorization');
    }
    public function description(): string
    {
        return 'Seed exact P10 creator, reviewer, and safety moderator authority.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260915112000_seed_p9_media_authorization_catalog')];
    }

    public function steps(): array
    {
        $steps = [];
        $position = 1;
        foreach (CommunityP10AuthorizationCatalog::PERMISSIONS as [$id, $code, $assurance]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_permission', $position++)),
                'Insert one P10 permission.',
                "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE',:assurance,'ACTIVE','community.recitation_clips',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code, ':assurance' => $assurance]
            );
        }
        foreach (CommunityP10AuthorizationCatalog::ROLES as [$id, $code]) {
            $steps[] = new SqlSeedStep(
                new SeedStepId(sprintf('%03d_role', $position++)),
                'Insert one P10 role.',
                "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE','ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)",
                [':id' => $id, ':code' => $code]
            );
        }
        foreach (CommunityP10AuthorizationCatalog::mappings() as $role => $codes) {
            foreach ($codes as $permission) {
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_mapping', $position++)),
                    'Map one P10 role permission.',
                    'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.code=:permission WHERE r.code=:role',
                    [':role' => $role, ':permission' => $permission]
                );
            }
        }
        return $steps;
    }
}
