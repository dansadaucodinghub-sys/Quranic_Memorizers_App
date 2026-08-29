<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

final readonly class PrivilegedAccessMaintenanceResult
{
    public function __construct(
        public int $expiredRequests,
        public int $expiredActivations,
        public int $overdueReviews,
        /** @var list<PrivilegedAccessMaintenanceNotification> */
        public array $notifications = [],
    ) {
    }
}
