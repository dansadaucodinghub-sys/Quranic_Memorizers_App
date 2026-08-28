<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\SecurityAuthorization;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationAccountRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationMembershipRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationRoleRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Identifier\UuidV7;

final class ConfigurableAuthorizationAdministrationRepository implements AuthorizationAdministrationRepository
{
    public ?AuthorizationAccountRecord $accountRecord = null;
    public ?AuthorizationRoleRecord $roleRecord = null;
    public ?AuthorizationMembershipRecord $membershipRecord = null;

    /** @var list<PermissionCode> */
    public array $rolePermissions = [];

    public function account(AccountId $id): ?AuthorizationAccountRecord
    {
        return $this->accountRecord;
    }

    public function role(RoleCode $code): ?AuthorizationRoleRecord
    {
        return $this->roleRecord?->code->equals($code) === true ? $this->roleRecord : null;
    }

    public function membership(TenantContext $context, UuidV7 $id): ?AuthorizationMembershipRecord
    {
        return $this->membershipRecord;
    }

    public function activeMembershipForAccount(
        TenantContext $context,
        int $accountInternalId,
    ): ?AuthorizationMembershipRecord {
        return $this->membershipRecord?->active === true
            && $this->membershipRecord->accountInternalId === $accountInternalId
            && $this->membershipRecord->workspaceInternalId === $context->workspaceInternalId()
            ? $this->membershipRecord
            : null;
    }

    public function activePermissionsForRole(AuthorizationRoleRecord $role, int $limit = 100): array
    {
        return array_slice($this->rolePermissions, 0, $limit);
    }
}
