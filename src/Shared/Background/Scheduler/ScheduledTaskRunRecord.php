<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;

final readonly class ScheduledTaskRunRecord
{
    public function __construct(
        public ScheduledExecutionSlot $slot,
        public SchedulerExecutionId $executionId,
        public ScheduledTaskRunStatus $status,
        public int $attempt,
        public DateTimeImmutable $leaseExpiresAt,
        public int $version,
        public ?string $failureCode,
    ) {
    }
}
