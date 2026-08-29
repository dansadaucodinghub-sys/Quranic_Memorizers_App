<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledPrivilegedAccessMaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private PrivilegedAccessMaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->maintenance->maintain($context->startedAt());

        return null;
    }
}
