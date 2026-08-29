<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain;

use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class WorkspaceContextOption
{
    public function __construct(
        public int $workspaceInternalId,
        public WorkspaceId $workspaceId,
        public string $workspaceName,
        public WorkspaceStatus $workspaceStatus,
        public int $workspaceVersion,
        public int $membershipInternalId,
        public UuidV7 $membershipId,
        public MembershipStatus $membershipStatus,
        public int $membershipVersion,
        public \DateTimeImmutable $workspaceUpdatedAt,
    ) {
        if (
            $workspaceInternalId < 1 || $membershipInternalId < 1 || trim($workspaceName) === ''
            || $workspaceVersion < 1 || $membershipVersion < 1
        ) {
            throw new \InvalidArgumentException('Workspace context option is invalid.');
        }
    }

    public function membershipIdentity(int $accountInternalId): ResolvedWorkspaceMembershipIdentity
    {
        return ResolvedWorkspaceMembershipIdentity::trusted(
            $this->membershipInternalId,
            $this->membershipId,
            $this->workspaceInternalId,
            $accountInternalId,
            $this->membershipStatus,
            $this->membershipVersion,
        );
    }
}
