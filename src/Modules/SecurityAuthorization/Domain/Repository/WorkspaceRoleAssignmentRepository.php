<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain\Repository;

use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\Tenancy\Application\TenantContext;

interface WorkspaceRoleAssignmentRepository
{
    public function add(TenantContext $context, WorkspaceRoleAssignment $assignment): int;

    public function findActiveAssignment(
        TenantContext $context,
        WorkspaceRoleAssignmentId $assignmentId,
        bool $forUpdate = false,
    ): ?WorkspaceRoleAssignment;

    public function findActiveForMembershipAndRole(
        TenantContext $context,
        int $membershipInternalId,
        int $roleInternalId,
        bool $forUpdate = false,
    ): ?WorkspaceRoleAssignment;

    /** @return list<WorkspaceRoleAssignment> */
    public function listActiveForMembership(
        TenantContext $context,
        int $membershipInternalId,
        int $limit = 50,
        bool $forUpdate = false,
    ): array;

    /** @return list<WorkspaceRoleAssignment> */
    public function listActiveForRole(TenantContext $context, int $roleInternalId, int $limit = 100): array;

    public function revoke(TenantContext $context, WorkspaceRoleAssignment $assignment): bool;

    public function countActiveWorkspaceOwners(TenantContext $context, bool $forUpdate = false): int;
}
