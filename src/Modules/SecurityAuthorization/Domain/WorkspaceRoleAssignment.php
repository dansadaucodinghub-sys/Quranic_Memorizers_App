<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;

final readonly class WorkspaceRoleAssignment
{
    public function __construct(
        public ?int $internalId,
        public WorkspaceRoleAssignmentId $id,
        public int $workspaceInternalId,
        public int $membershipInternalId,
        public int $accountInternalId,
        public int $roleInternalId,
        public RoleCode $roleCode,
        public RoleAssignmentStatus $status,
        public RoleAssignmentVersion $version,
        public RoleAssignmentActorKind $assignedByKind,
        public ?int $assignedByAccountInternalId,
        public RoleAssignmentReasonCode $assignmentReason,
        public DateTimeImmutable $assignedAt,
        public ?RoleAssignmentActorKind $revokedByKind,
        public ?int $revokedByAccountInternalId,
        public ?RoleAssignmentReasonCode $revocationReason,
        public ?DateTimeImmutable $revokedAt,
        public string $correlationId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (
            $workspaceInternalId < 1
            || $membershipInternalId < 1
            || $accountInternalId < 1
            || $roleInternalId < 1
            || strlen($correlationId) !== 32
        ) {
            throw new \InvalidArgumentException('Workspace role assignment is invalid.');
        }
        $assignedByKind->assertActor($assignedByAccountInternalId);
        if ($status === RoleAssignmentStatus::ACTIVE && $revokedAt !== null) {
            throw new \InvalidArgumentException('Active role assignment cannot have revocation state.');
        }
        if ($status === RoleAssignmentStatus::REVOKED) {
            if ($revokedByKind === null || $revocationReason === null || $revokedAt === null) {
                throw new \InvalidArgumentException('Revoked role assignment requires revocation state.');
            }
            $revokedByKind->assertActor($revokedByAccountInternalId);
        }
    }

    public function revoke(
        RoleAssignmentActorKind $actorKind,
        ?int $actorAccountInternalId,
        RoleAssignmentReasonCode $reason,
        DateTimeImmutable $at,
    ): self {
        if ($this->status !== RoleAssignmentStatus::ACTIVE) {
            throw new \DomainException('Only an active role assignment may be revoked.');
        }

        return new self(
            $this->internalId,
            $this->id,
            $this->workspaceInternalId,
            $this->membershipInternalId,
            $this->accountInternalId,
            $this->roleInternalId,
            $this->roleCode,
            RoleAssignmentStatus::REVOKED,
            $this->version->next(),
            $this->assignedByKind,
            $this->assignedByAccountInternalId,
            $this->assignmentReason,
            $this->assignedAt,
            $actorKind,
            $actorAccountInternalId,
            $reason,
            $at,
            $this->correlationId,
            $this->createdAt,
            $at,
        );
    }
}
