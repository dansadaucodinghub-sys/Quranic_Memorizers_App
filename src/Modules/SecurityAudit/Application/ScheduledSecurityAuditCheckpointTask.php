<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditCheckpointService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

final readonly class ScheduledSecurityAuditCheckpointTask implements ScheduledTaskHandler
{
    public function __construct(private SecurityAuditCheckpointService $checkpoints)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $this->checkpoints->createWhenChanged();

        return null;
    }
}
