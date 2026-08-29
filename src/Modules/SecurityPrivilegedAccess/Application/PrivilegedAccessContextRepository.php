<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedWorkspaceTenantContext;

interface PrivilegedAccessContextRepository
{
    /** @return array{context: PrivilegedAccessContext, workspace: ?PrivilegedWorkspaceTenantContext}|null */
    public function activeContext(AuthenticatedAccountContext $actor, DateTimeImmutable $now): ?array;

    /**
     * Invalidates the active exceptional activation when corruption attempts to combine it
     * with a normal selected workspace. The caller owns the transaction.
     */
    public function invalidateConflictingTenantContext(AuthenticatedAccountContext $actor, DateTimeImmutable $now): void;
}
