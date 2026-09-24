<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

final readonly class CommunityNotificationDeliveryResult
{
    public function __construct(
        public int $claimed,
        public int $delivered,
        public int $retried,
        public int $deadLettered,
        public int $stale,
    ) {
    }
}
