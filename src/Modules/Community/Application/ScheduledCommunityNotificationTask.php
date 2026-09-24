<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledCommunityNotificationTask implements ScheduledTaskHandler
{
    public function __construct(private CommunityNotificationDeliveryService $delivery)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->delivery->deliverDue();
        return null;
    }
}
