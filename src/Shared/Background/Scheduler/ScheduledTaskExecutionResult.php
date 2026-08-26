<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

final readonly class ScheduledTaskExecutionResult
{
    public function __construct(
        public ScheduledTaskId $taskId,
        public ScheduledTaskExecutionOutcome $outcome,
        public ScheduledTaskClaimDisposition $claimDisposition,
    ) {
    }
}
