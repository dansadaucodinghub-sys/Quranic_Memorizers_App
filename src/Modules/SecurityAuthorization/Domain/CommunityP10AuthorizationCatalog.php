<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class CommunityP10AuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    public const array PERMISSIONS = [
        ['01a0a000-0000-7001-8000-000000000001', 'community.clips.create', 'PRIMARY'],
        ['01a0a000-0000-7001-8000-000000000002', 'community.clips.manage_own', 'PRIMARY'],
        ['01a0a000-0000-7001-8000-000000000003', 'workspace.community.clips.review', 'PHISHING_RESISTANT'],
        ['01a0a000-0000-7001-8000-000000000004', 'workspace.community.reports.view', 'MULTI_FACTOR'],
        ['01a0a000-0000-7001-8000-000000000005', 'workspace.community.moderation.review', 'MULTI_FACTOR'],
        ['01a0a000-0000-7001-8000-000000000006', 'workspace.community.moderation.decide', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string}> */
    public const array ROLES = [
        ['01a0a000-0000-7001-8000-000000000021', 'workspace.community_creator'],
        ['01a0a000-0000-7001-8000-000000000022', 'workspace.community_reviewer'],
        ['01a0a000-0000-7001-8000-000000000023', 'workspace.community_moderator'],
    ];

    /** @return array<string,list<string>> */
    public static function mappings(): array
    {
        return [
            'workspace.owner' => array_column(self::PERMISSIONS, 1),
            'workspace.community_creator' => ['community.clips.create', 'community.clips.manage_own'],
            'workspace.community_reviewer' => ['workspace.community.clips.review'],
            'workspace.community_moderator' => ['workspace.community.reports.view',
                'workspace.community.moderation.review', 'workspace.community.moderation.decide'],
        ];
    }

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-22T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::WORKSPACE,
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'community.recitation_clips',
                1,
                $time,
                $time,
            ));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(
                RoleId::fromString($id),
                new RoleCode($code),
                AuthorizationScopeType::WORKSPACE,
                RoleStatus::ACTIVE,
                true,
                1,
                $time,
                $time
            ));
        }
        foreach (self::mappings() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }
}
