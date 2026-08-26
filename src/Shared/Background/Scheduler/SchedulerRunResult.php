<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

final readonly class SchedulerRunResult
{
    /** @param list<ScheduledTaskExecutionResult> $results */
    public function __construct(
        public int $due,
        public int $claimed,
        public int $succeeded,
        public int $failed,
        public int $skipped,
        public array $results,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->failed === 0;
    }
}
