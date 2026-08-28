<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain\Repository;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationAccountRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationMembershipRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationRoleRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Identifier\UuidV7;

interface AuthorizationAdministrationRepository
{
    public function account(AccountId $id): ?AuthorizationAccountRecord;

    public function role(RoleCode $code): ?AuthorizationRoleRecord;

    public function membership(TenantContext $context, UuidV7 $id): ?AuthorizationMembershipRecord;

    public function activeMembershipForAccount(
        TenantContext $context,
        int $accountInternalId,
    ): ?AuthorizationMembershipRecord;

    /** @return list<PermissionCode> */
    public function activePermissionsForRole(AuthorizationRoleRecord $role, int $limit = 100): array;
}
