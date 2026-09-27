<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledP13MaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private P13MaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $operation = match ($context->taskId()->value()) {
            'pilot.readiness.reconcile' => 'pilot:readiness',
            'pilot.health.snapshot' => 'pilot:health',
            'rollout.waves.process' => 'rollout:waves',
            'rollout.health.snapshot' => 'rollout:health',
            'offline.packages.process' => 'packages:prepare',
            'offline.packages.expire' => 'packages:expire',
            'offline.packages.reconcile' => 'packages:reconcile',
            'offline.devices.reconcile' => 'devices:reconcile',
            'offline.sync.reconcile' => 'sync:reconcile',
            'offline.conflicts.notify' => 'conflicts:notify',
            'offline.receipts.reconcile' => 'receipts:reconcile',
            default => throw new \RuntimeException('Unknown P13 scheduled task.'),
        };
        $this->maintenance->run($operation);

        return null;
    }
}
