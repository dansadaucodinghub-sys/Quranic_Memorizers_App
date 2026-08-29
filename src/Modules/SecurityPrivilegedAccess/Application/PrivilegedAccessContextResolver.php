<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessContextResolver
{
    public function __construct(private PrivilegedAccessContextRepository $contexts, private Clock $clock)
    {
    }

    /** @return array{context: \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessContext, workspace: ?\Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedWorkspaceTenantContext}|null */
    public function resolve(AuthenticatedAccountContext $actor): ?array
    {
        return $this->contexts->activeContext($actor, $this->clock->now());
    }

    public function invalidateConflictingTenantContext(AuthenticatedAccountContext $actor, DateTimeImmutable $now): void
    {
        $this->contexts->invalidateConflictingTenantContext($actor, $now);
    }
}
