<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\SecurityPrivilegedAccess;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessDuration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessJustification;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessPermissionPolicyCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;

final class PrivilegedAccessDomainTest extends TestCase
{
    public function testPrivilegedCatalogExtensionIsFixedAndMappedWithoutWildcardPermissions(): void
    {
        $catalog = AuthorizationCatalogRegistry::withPrivilegedAccess();

        self::assertCount(27, $catalog->permissions());
        self::assertCount(9, $catalog->roles());
        self::assertCount(73, $catalog->mappings());
        self::assertNotNull($catalog->permission(new \Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode(
            'platform.break_glass.activate',
        )));
        self::assertNotNull($catalog->role(new \Qmdb\Modules\SecurityAuthorization\Domain\RoleCode(
            'platform.privileged_access_administrator',
        )));
    }

    public function testPolicyAllowsOnlyExactScopeAndAccessTypeCombinations(): void
    {
        self::assertCount(20, PrivilegedAccessPermissionPolicyCatalog::entries());
        self::assertTrue(PrivilegedAccessPermissionPolicyCatalog::permits(
            PrivilegedAccessType::SUPPORT_ACCESS,
            'workspace.security.view',
            AuthorizationScopeType::WORKSPACE,
        ));
        self::assertFalse(PrivilegedAccessPermissionPolicyCatalog::permits(
            PrivilegedAccessType::SUPPORT_ACCESS,
            'workspace.memberships.manage',
            AuthorizationScopeType::WORKSPACE,
        ));
        self::assertFalse(PrivilegedAccessPermissionPolicyCatalog::permits(
            PrivilegedAccessType::TEMPORARY_PRIVILEGE,
            'platform.authorization.assign',
            AuthorizationScopeType::PLATFORM,
        ));
    }

    public function testDurationsStatusesAndConfidentialJustificationsFailClosed(): void
    {
        self::assertSame(120, PrivilegedAccessDuration::requested(120, 120)->seconds);
        self::expectException(\InvalidArgumentException::class);
        PrivilegedAccessDuration::requested(121, 120);
    }

    public function testLifecycleTransitionsAndConfidentialJustificationAreConstrained(): void
    {
        self::assertTrue(PrivilegedAccessRequestStatus::REQUESTED->permits(
            PrivilegedAccessRequestStatus::PARTIALLY_APPROVED,
            PrivilegedAccessType::SUPPORT_ACCESS,
        ));
        self::assertFalse(PrivilegedAccessRequestStatus::APPROVED->permits(
            PrivilegedAccessRequestStatus::REQUESTED,
            PrivilegedAccessType::TEMPORARY_PRIVILEGE,
        ));
        self::expectException(\InvalidArgumentException::class);
        PrivilegedAccessJustification::fromInput('<script>unsafe</script>', 2000);
    }
}
