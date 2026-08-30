<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class AccountStateAuthorizationCatalog
{
    /** @var list<array{string, string, string}> */
    private const array PERMISSIONS = [
        ['01a0474d-bd09-70aa-8aa1-000000000001', 'platform.accounts.view', 'MULTI_FACTOR'],
        ['01a0474d-bd09-70aa-8aa1-000000000002', 'platform.accounts.suspend', 'PHISHING_RESISTANT'],
        ['01a0474d-bd09-70aa-8aa1-000000000003', 'platform.accounts.reactivate', 'PHISHING_RESISTANT'],
        ['01a0474d-bd09-70aa-8aa1-000000000004', 'platform.security_events.view', 'MULTI_FACTOR'],
        ['01a0474d-bd09-70aa-8aa1-000000000005', 'platform.audit.verify', 'PHISHING_RESISTANT'],
    ];

    /** @var array<string, list<string>> */
    private const array MAPPINGS = [
        'platform.security_administrator' => [
            'platform.accounts.view', 'platform.accounts.suspend', 'platform.accounts.reactivate',
            'platform.security_events.view', 'platform.audit.verify',
        ],
        'platform.authorization_auditor' => [
            'platform.accounts.view', 'platform.security_events.view', 'platform.audit.verify',
        ],
        'platform.privileged_access_administrator' => ['platform.security_events.view', 'platform.audit.verify'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $timestamp = new DateTimeImmutable('2026-08-29T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(
                PermissionId::fromString($id),
                new PermissionCode($code),
                AuthorizationScopeType::PLATFORM,
                AuthenticationAssuranceLevel::from($assurance),
                PermissionStatus::ACTIVE,
                'identity.account_state',
                1,
                $timestamp,
                $timestamp,
            ));
        }
        foreach (self::MAPPINGS as $roleCode => $permissionCodes) {
            foreach ($permissionCodes as $permissionCode) {
                $builder->map(new RoleCode($roleCode), new PermissionCode($permissionCode));
            }
        }
    }

    /** @return list<string> */
    public static function permissionCodes(): array
    {
        return array_map(static fn (array $entry): string => $entry[1], self::PERMISSIONS);
    }
}
