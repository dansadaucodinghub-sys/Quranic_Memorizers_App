<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\SecurityAuthorization;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogBuilder;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionId;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;

final class AuthorizationCatalogTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidPermissionCodes(): iterable
    {
        foreach (['*', 'platform.*', 'Platform.security.view', 'platform..view', 'platform view all'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('invalidPermissionCodes')]
    public function testPermissionCodesRejectWildcardsAndNonCanonicalValues(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PermissionCode($code);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRoleCodes(): iterable
    {
        foreach (['*', 'workspace.*', 'Workspace.owner', 'workspace owner'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('invalidRoleCodes')]
    public function testRoleCodesRejectWildcardsAndNonCanonicalValues(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoleCode($code);
    }

    public function testFoundationalCatalogIsExplicitDeterministicAndScopeSafe(): void
    {
        $catalog = AuthorizationCatalogRegistry::foundational();
        $permissionCodes = array_map(
            static fn ($definition): string => $definition->code->value(),
            $catalog->permissions(),
        );
        $roleCodes = array_map(static fn ($definition): string => $definition->code->value(), $catalog->roles());

        self::assertCount(10, $permissionCodes);
        self::assertCount(7, $roleCodes);
        self::assertCount(27, $catalog->mappings());
        self::assertSame($permissionCodes, array_values(array_unique($permissionCodes)));
        self::assertSame($roleCodes, array_values(array_unique($roleCodes)));
        self::assertSame($permissionCodes, [...$permissionCodes]);
        self::assertSame($permissionCodes, $this->sorted($permissionCodes));
        self::assertSame($roleCodes, $this->sorted($roleCodes));
        self::assertFalse((bool) preg_grep('/\*/', [...$permissionCodes, ...$roleCodes]));
        foreach ($catalog->mappings() as $mapping) {
            self::assertSame($mapping->scopeType, $catalog->role($mapping->roleCode)?->scopeType);
            self::assertSame($mapping->scopeType, $catalog->permission($mapping->permissionCode)?->scopeType);
        }
    }

    public function testCatalogBuilderRejectsDuplicatesMissingReferencesCrossScopeAndMutationAfterBuild(): void
    {
        $platformPermission = $this->permission('01a0477c-435f-7c5e-9407-67e5bc31f66b', 'platform.audit.events');
        $workspacePermission = $this->permission(
            '01a0477c-435f-7925-8937-f0cc65778765',
            'workspace.audit.events',
            AuthorizationScopeType::WORKSPACE,
        );
        $platformRole = $this->role('01a0477c-435f-72b0-a400-6838710b46d8', 'platform.audit_reader');
        $builder = (new AuthorizationCatalogBuilder())->permission($platformPermission)->role($platformRole);

        try {
            $builder->permission($platformPermission);
            self::fail('Duplicate permission must be rejected.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
        try {
            $builder->role($platformRole);
            self::fail('Duplicate role must be rejected.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
        try {
            (new AuthorizationCatalogBuilder())->permission($platformPermission)->role(new RoleDefinition(
                RoleId::fromString($platformPermission->id->toString()),
                new RoleCode('platform.duplicate_id'),
                AuthorizationScopeType::PLATFORM,
                RoleStatus::ACTIVE,
                true,
                1,
                new DateTimeImmutable('2026-08-28T00:00:00Z'),
                new DateTimeImmutable('2026-08-28T00:00:00Z'),
            ));
            self::fail('Duplicate public ID must be rejected.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
        try {
            $builder->map(new RoleCode('platform.audit_reader'), new PermissionCode('platform.missing.permission'));
            self::fail('Missing mapping reference must be rejected.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
        $crossScope = (new AuthorizationCatalogBuilder())
            ->permission($workspacePermission)
            ->role($platformRole);
        try {
            $crossScope->map($platformRole->code, $workspacePermission->code);
            self::fail('Cross-scope mapping must be rejected.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }
        $builder->map($platformRole->code, $platformPermission->code)->build();
        $this->expectException(LogicException::class);
        $builder->permission($this->permission(
            '01a0477c-435f-7142-9c44-579ebf6f78c2',
            'platform.audit.more',
        ));
    }

    public function testAssuranceComparisonAllowsExactOrStrongerAndRejectsWeaker(): void
    {
        $comparator = new AuthenticationAssuranceComparator();

        self::assertTrue($comparator->satisfies(
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ));
        self::assertTrue($comparator->satisfies(
            AuthenticationAssuranceLevel::PHISHING_RESISTANT,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ));
        self::assertFalse($comparator->satisfies(
            AuthenticationAssuranceLevel::PRIMARY,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
        ));
    }

    private function permission(
        string $id,
        string $code,
        AuthorizationScopeType $scope = AuthorizationScopeType::PLATFORM,
    ): PermissionDefinition {
        $now = new DateTimeImmutable('2026-08-28T00:00:00Z');

        return new PermissionDefinition(
            PermissionId::fromString($id),
            new PermissionCode($code),
            $scope,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            PermissionStatus::ACTIVE,
            'security.authorization',
            1,
            $now,
            $now,
        );
    }

    private function role(string $id, string $code): RoleDefinition
    {
        $now = new DateTimeImmutable('2026-08-28T00:00:00Z');

        return new RoleDefinition(
            RoleId::fromString($id),
            new RoleCode($code),
            AuthorizationScopeType::PLATFORM,
            RoleStatus::ACTIVE,
            true,
            1,
            $now,
            $now,
        );
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values, SORT_STRING);

        return $values;
    }
}
