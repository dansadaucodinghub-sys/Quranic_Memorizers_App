<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class PeopleIdentityResolutionAuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a0474d-b505-7000-8000-000000000001', 'platform.people_profiles.view', 'MULTI_FACTOR'],
        ['01a0474d-b505-7000-8000-000000000002', 'platform.people_profile_claims.authorize', 'PHISHING_RESISTANT'],
        ['01a0474d-b505-7000-8000-000000000003', 'platform.people_profile_verifications.manage', 'PHISHING_RESISTANT'],
        ['01a0474d-b505-7000-8000-000000000004', 'platform.people_duplicates.view', 'MULTI_FACTOR'],
        ['01a0474d-b505-7000-8000-000000000005', 'platform.people_duplicates.resolve', 'PHISHING_RESISTANT'],
    ];

    /** @var list<array{string,string}> */
    private const array ROLES = [
        ['01a0474d-b505-7001-8000-000000000001', 'platform.people_profile_reviewer'],
    ];

    /** @var array<string,list<string>> */
    private const array MAPPINGS = [
        'platform.security_administrator' => ['platform.people_profiles.view', 'platform.people_profile_claims.authorize', 'platform.people_profile_verifications.manage', 'platform.people_duplicates.view', 'platform.people_duplicates.resolve'],
        'platform.people_profile_reviewer' => ['platform.people_profiles.view', 'platform.people_profile_claims.authorize', 'platform.people_profile_verifications.manage', 'platform.people_duplicates.view', 'platform.people_duplicates.resolve'],
        'platform.authorization_auditor' => ['platform.people_profiles.view', 'platform.people_duplicates.view'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-09-02T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::PLATFORM, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'people.identity_resolution', 1, $timestamp, $timestamp));
        }
        foreach (self::ROLES as [$id, $code]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::PLATFORM, RoleStatus::ACTIVE, true, 1, $timestamp, $timestamp));
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
        return array_map(static fn (array $permission): string => $permission[1], self::PERMISSIONS);
    }
}
