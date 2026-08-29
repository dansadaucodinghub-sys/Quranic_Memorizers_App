<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;

interface PrivilegedAccessMaintenanceRepository
{
    public function maintain(DateTimeImmutable $now, int $limit, int $reviewTtlSeconds): PrivilegedAccessMaintenanceResult;
}
