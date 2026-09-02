<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledProfileClaimMaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private ProfileClaimMaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->maintenance->maintain();

        return null;
    }
}
