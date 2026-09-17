<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Exact P9 evidence permissions. It grants no account or public role. */
final readonly class SeedP9MediaAuthorizationCatalog implements Seed
{
    public function id(): SeedId { return new SeedId('20260915112000_seed_p9_media_authorization_catalog'); }
    public function description(): string { return 'Seed P9 private audio and video evidence authorization catalog.'; }
    public function dependencies(): array { return [new SeedId('20260915105000_seed_p8_authorization_catalog')]; }

    public function steps(): array
    {
        $permissions = [
            ['01a09000-0000-7001-8000-000000000001', 'media.assets.view_own', 'WORKSPACE', 'media.catalog'],
            ['01a09000-0000-7001-8000-000000000002', 'media.assets.upload', 'WORKSPACE', 'media.ingestion'],
            ['01a09000-0000-7001-8000-000000000003', 'media.assets.manage_own', 'WORKSPACE', 'media.ingestion'],
            ['01a09000-0000-7001-8000-000000000004', 'workspace.media.view', 'WORKSPACE', 'media.catalog'],
            ['01a09000-0000-7001-8000-000000000005', 'workspace.media.review', 'WORKSPACE', 'media.moderation'],
            ['01a09000-0000-7001-8000-000000000006', 'workspace.media.approve', 'WORKSPACE', 'media.moderation'],
            ['01a09000-0000-7001-8000-000000000007', 'workspace.media.reject', 'WORKSPACE', 'media.moderation'],
            ['01a09000-0000-7001-8000-000000000008', 'workspace.media.hold', 'WORKSPACE', 'media.moderation'],
            ['01a09000-0000-7001-8000-000000000009', 'workspace.media.remove', 'WORKSPACE', 'media.moderation'],
            ['01a09000-0000-7001-8000-000000000010', 'workspace.media.audit', 'WORKSPACE', 'media.catalog'],
        ];
        $steps = [];
        foreach ($permissions as $index => [$id, $code, $scope, $module]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $index + 1)), 'Insert one exact P9 permission.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'PHISHING_RESISTANT','ACTIVE',:module,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':scope' => $scope, ':module' => $module]);
        }
        $roles = [
            ['01a09000-0000-7001-8000-000000000021', 'workspace.media_creator'], ['01a09000-0000-7001-8000-000000000022', 'workspace.media_reviewer'], ['01a09000-0000-7001-8000-000000000023', 'workspace.media_manager'], ['01a09000-0000-7001-8000-000000000024', 'workspace.media_auditor'],
        ];
        foreach ($roles as $index => [$id, $code]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_role', $index + 11)), 'Insert one narrow P9 role.', "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,'WORKSPACE','ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code]);
        }
        $mappings = [
            'workspace.owner' => array_column($permissions, 1),
            'workspace.administrator' => ['media.assets.view_own', 'media.assets.upload', 'media.assets.manage_own', 'workspace.media.view', 'workspace.media.review'],
            'workspace.media_creator' => ['media.assets.view_own', 'media.assets.upload', 'media.assets.manage_own'],
            'workspace.media_reviewer' => ['workspace.media.view', 'workspace.media.review'],
            'workspace.media_manager' => ['workspace.media.view', 'workspace.media.review', 'workspace.media.approve', 'workspace.media.reject', 'workspace.media.hold', 'workspace.media.remove'],
            'workspace.media_auditor' => ['workspace.media.view', 'workspace.media.audit'],
        ];
        $position = 15;
        foreach ($mappings as $role => $codes) foreach ($codes as $permission) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position++)), 'Map one exact P9 role permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT role_definition.id,role_definition.scope_type,permission_definition.id,permission_definition.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles role_definition INNER JOIN authorization_permissions permission_definition ON permission_definition.code=:permission_code WHERE role_definition.code=:role_code", [':role_code' => $role, ':permission_code' => $permission]);
        }
        return $steps;
    }
}
