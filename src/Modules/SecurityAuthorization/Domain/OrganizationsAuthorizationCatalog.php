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
        ['01a0474d-b404-7000-8000-000000000001', 'workspace.organization_affiliations.view', 'PRIMARY'],
        ['01a0474d-b404-7000-8000-000000000002', 'workspace.organization_affiliations.manage', 'MULTI_FACTOR'],
        ['01a0474d-b404-7000-8000-000000000003', 'workspace.organization_affiliation_assignments.manage', 'MULTI_FACTOR'],
        ['01a0474d-b404-7000-8000-000000000004', 'workspace.organization_leadership.manage', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string,string}> */
    private const array ROLES = [
        ['01a0474d-b404-7001-8000-000000000001', 'workspace.organization_people_manager', 'WORKSPACE'],
    ];

    /** @var array<string,list<string>> */
    private const array MAPPINGS = [
        'workspace.owner' => ['workspace.organizations.view', 'workspace.organizations.manage', 'workspace.organization_units.view', 'workspace.organization_units.manage', 'workspace.organization_affiliations.view', 'workspace.organization_affiliations.manage', 'workspace.organization_affiliation_assignments.manage', 'workspace.organization_leadership.manage'],
        'workspace.administrator' => ['workspace.organizations.view', 'workspace.organizations.manage', 'workspace.organization_units.view', 'workspace.organization_units.manage', 'workspace.organization_affiliations.view', 'workspace.organization_affiliations.manage', 'workspace.organization_affiliation_assignments.manage', 'workspace.organization_leadership.manage'],
        'workspace.security_manager' => ['workspace.organizations.view', 'workspace.organization_units.view'],
        'workspace.membership_manager' => ['workspace.organizations.view', 'workspace.organization_units.view'],
        'workspace.viewer' => ['workspace.organizations.view', 'workspace.organization_units.view'],
        'workspace.organization_people_manager' => ['workspace.organizations.view', 'workspace.organization_units.view', 'workspace.organization_affiliations.view', 'workspace.organization_affiliations.manage', 'workspace.organization_affiliation_assignments.manage'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-09-01T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'organizations.registry', 1, $timestamp, $timestamp));
        }
        foreach (self::ROLES as [$id, $code, $scope]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::from($scope), RoleStatus::ACTIVE, true, 1, $timestamp, $timestamp));
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
