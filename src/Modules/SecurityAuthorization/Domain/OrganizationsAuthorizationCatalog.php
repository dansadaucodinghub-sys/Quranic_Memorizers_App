<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Authorization extension owned by organizations.registry, never by a UI client. */
final readonly class OrganizationsAuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a0474d-b303-7000-8000-000000000001', 'workspace.organizations.view', 'PRIMARY'],
        ['01a0474d-b303-7000-8000-000000000002', 'workspace.organizations.manage', 'MULTI_FACTOR'],
        ['01a0474d-b303-7000-8000-000000000003', 'workspace.organization_units.view', 'PRIMARY'],
        ['01a0474d-b303-7000-8000-000000000004', 'workspace.organization_units.manage', 'MULTI_FACTOR'],
    ];

    /** @var array<string,list<string>> */
    private const array MAPPINGS = [
        'workspace.owner' => ['workspace.organizations.view', 'workspace.organizations.manage', 'workspace.organization_units.view', 'workspace.organization_units.manage'],
        'workspace.administrator' => ['workspace.organizations.view', 'workspace.organizations.manage', 'workspace.organization_units.view', 'workspace.organization_units.manage'],
        'workspace.security_manager' => ['workspace.organizations.view', 'workspace.organization_units.view'],
        'workspace.membership_manager' => ['workspace.organizations.view', 'workspace.organization_units.view'],
        'workspace.viewer' => ['workspace.organizations.view', 'workspace.organization_units.view'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-09-01T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'organizations.registry', 1, $timestamp, $timestamp));
        }
        foreach (self::MAPPINGS as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }

    /** @return list<string> */
    public static function permissionCodes(): array
    {
        return array_map(static fn (array $entry): string => $entry[1], self::PERMISSIONS);
    }
}
