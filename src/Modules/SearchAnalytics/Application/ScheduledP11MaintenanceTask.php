<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Application;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\P11MaintenanceService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledP11MaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private P11MaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'analytics.snapshots.process' => $this->maintenance->processSnapshots(false, 25),
            'analytics.snapshots.reconcile' => $this->maintenance->reconcileSnapshots(),
            'reports.process' => $this->maintenance->processReports(false, 10),
            'reports.reconcile' => $this->maintenance->reconcileReports(),
            'exports.cleanup' => $this->maintenance->cleanupExports(false, 50),
            default => throw new \RuntimeException('Unknown P11 scheduled task.'),
        };
        return null;
    }
}
