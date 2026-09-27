<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Application;

use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\P12MaintenanceService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledP12MaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private P12MaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'outbox.publish' => $this->maintenance->processOutbox(false),
            'notifications.deliver' => $this->maintenance->processNotifications(false),
            'webhooks.deliver' => $this->maintenance->processWebhooks(false),
            'p12.work.reconcile' => $this->maintenance->reconcile(),
            'privacy.retention.process' => $this->maintenance->retention(false),
            'operations.cleanup' => $this->maintenance->cleanup(false),
            'audit.lineage.verify' => $this->maintenance->verifyAudit(),
            'backups.metadata.verify' => $this->maintenance->verifyBackups(),
            default => throw new \RuntimeException('Unknown P12 scheduled task.'),
        };
        return null;
    }
}
