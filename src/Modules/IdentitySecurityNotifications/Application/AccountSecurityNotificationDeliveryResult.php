<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application;

final readonly class AccountSecurityNotificationDeliveryResult
{
    public function __construct(
        public int $claimed,
        public int $delivered,
        public int $retried,
        public int $failed,
        public int $stale,
    ) {
    }
}
