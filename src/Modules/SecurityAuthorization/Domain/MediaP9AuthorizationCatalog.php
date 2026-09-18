<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Exact P9 authorization vocabulary mirrored by the P9 seed. */
final readonly class MediaP9AuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a09000-0000-7001-8000-000000000001', 'media.assets.view_own', 'media.catalog'], ['01a09000-0000-7001-8000-000000000002', 'media.assets.upload', 'media.ingestion'], ['01a09000-0000-7001-8000-000000000003', 'media.assets.manage_own', 'media.ingestion'], ['01a09000-0000-7001-8000-000000000004', 'workspace.media.view', 'media.catalog'], ['01a09000-0000-7001-8000-000000000005', 'workspace.media.review', 'media.moderation'], ['01a09000-0000-7001-8000-000000000006', 'workspace.media.approve', 'media.moderation'], ['01a09000-0000-7001-8000-000000000007', 'workspace.media.reject', 'media.moderation'], ['01a09000-0000-7001-8000-000000000008', 'workspace.media.hold', 'media.moderation'], ['01a09000-0000-7001-8000-000000000009', 'workspace.media.remove', 'media.moderation'], ['01a09000-0000-7001-8000-000000000010', 'workspace.media.audit', 'media.catalog'],
    ];
    /** @var list<array{string,string}> */
    private const array ROLES = [
        ['01a09000-0000-7001-8000-000000000021', 'workspace.media_creator'], ['01a09000-0000-7001-8000-000000000022', 'workspace.media_reviewer'], ['01a09000-0000-7001-8000-000000000023', 'workspace.media_manager'], ['01a09000-0000-7001-8000-000000000024', 'workspace.media_auditor'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-15T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $module]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::PHISHING_RESISTANT, PermissionStatus::ACTIVE, $module, 1, $time, $time));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::WORKSPACE, RoleStatus::ACTIVE, true, 1, $time, $time));
        }
        $mappings = [
            'workspace.owner' => array_column(self::PERMISSIONS, 1),
            'workspace.administrator' => ['media.assets.view_own', 'media.assets.upload', 'media.assets.manage_own', 'workspace.media.view', 'workspace.media.review'],
            'workspace.media_creator' => ['media.assets.view_own', 'media.assets.upload', 'media.assets.manage_own'],
            'workspace.media_reviewer' => ['workspace.media.view', 'workspace.media.review'],
            'workspace.media_manager' => ['workspace.media.view', 'workspace.media.review', 'workspace.media.approve', 'workspace.media.reject', 'workspace.media.hold', 'workspace.media.remove'],
            'workspace.media_auditor' => ['workspace.media.view', 'workspace.media.audit'],
        ];
        foreach ($mappings as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }
}
