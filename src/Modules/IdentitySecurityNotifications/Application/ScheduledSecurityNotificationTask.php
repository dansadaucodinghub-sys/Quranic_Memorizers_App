<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledSecurityNotificationTask implements ScheduledTaskHandler
{
    public function __construct(private AccountSecurityNotificationDeliveryService $delivery)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->delivery->deliverDue();

        return null;
    }
}
