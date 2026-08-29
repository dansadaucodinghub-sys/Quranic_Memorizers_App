<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessActivationId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;

interface PrivilegedAccessActivationRepository
{
    public function activate(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        PrivilegedAccessType $expectedType,
        int $tenantContextVersion,
        string $correlationId,
        DateTimeImmutable $now,
        ?DateTimeImmutable $reviewDueAt,
    ): PrivilegedAccessActivationId;
}
