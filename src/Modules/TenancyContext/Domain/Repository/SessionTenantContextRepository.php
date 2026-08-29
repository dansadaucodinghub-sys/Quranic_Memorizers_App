<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Domain\SessionTenantContextState;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Modules\TenancyContext\Domain\WorkspaceContextOption;

interface SessionTenantContextRepository
{
    public function state(AuthenticatedAccountContext $account, bool $forUpdate = false): SessionTenantContextState;

    /** @return list<WorkspaceContextOption> */
    public function availableForAccount(
        AuthenticatedAccountContext $account,
        int $limit = 50,
        ?WorkspaceId $afterWorkspaceId = null,
    ): array;

    public function selectable(
        AuthenticatedAccountContext $account,
        WorkspaceId $workspaceId,
        bool $forUpdate = false,
    ): ?WorkspaceContextOption;

    public function select(
        AuthenticatedAccountContext $account,
        WorkspaceContextOption $workspace,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $selectedAt,
    ): bool;

    public function clear(
        AuthenticatedAccountContext $account,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $updatedAt,
    ): bool;
}
