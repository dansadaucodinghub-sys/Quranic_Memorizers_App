<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\Workspace;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;

interface WorkspaceRepository
{
    public function create(Workspace $workspace): int;

    public function byPublicId(WorkspaceId $workspaceId): ?Workspace;

    public function changeStatus(
        WorkspaceId $workspaceId,
        WorkspaceStatus $status,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool;
}
