<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain\Repository;

use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Application\TenantScopedRepository;
use Qmdb\Modules\Tenancy\Domain\WorkspaceMembership;

interface WorkspaceMembershipRepository extends TenantScopedRepository
{
    public function create(TenantContext $context, WorkspaceMembership $membership): int;

    /** @return list<WorkspaceMembership> */
    public function forAccount(TenantContext $context, int $accountInternalId, int $limit = 50): array;
}
