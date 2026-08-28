<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain\Repository;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;

interface PlatformRoleAssignmentRepository
{
    public function add(PlatformRoleAssignment $assignment): int;

    public function findActiveAssignment(
        AccountId $accountId,
        PlatformRoleAssignmentId $assignmentId,
        bool $forUpdate = false,
    ): ?PlatformRoleAssignment;

    public function findActiveForAccountAndRole(
        int $accountInternalId,
        int $roleInternalId,
        bool $forUpdate = false,
    ): ?PlatformRoleAssignment;

    /** @return list<PlatformRoleAssignment> */
    public function listActiveForAccount(int $accountInternalId, int $limit = 50, bool $forUpdate = false): array;

    /** @return list<PlatformRoleAssignment> */
    public function listActiveForRole(int $roleInternalId, int $limit = 100): array;

    public function revoke(PlatformRoleAssignment $assignment): bool;

    public function countActivePlatformSecurityAdministrators(bool $forUpdate = false): int;
}
