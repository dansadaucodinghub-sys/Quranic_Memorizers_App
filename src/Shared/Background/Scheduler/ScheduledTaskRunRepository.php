<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;

interface ScheduledTaskRunRepository
{
    public function claim(
        ScheduledExecutionSlot $slot,
        SchedulerExecutionId $executionId,
        DateTimeImmutable $now,
        int $leaseSeconds,
    ): ScheduledTaskRunClaim;

    public function markRunning(ScheduledTaskRunClaim $claim, DateTimeImmutable $startedAt): ScheduledTaskRunClaim;

    public function markSucceeded(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $completedAt,
        int $durationMilliseconds,
    ): void;

    public function markFailed(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $failedAt,
        int $durationMilliseconds,
        string $safeFailureCode,
    ): void;

    public function find(ScheduledExecutionSlot $slot): ?ScheduledTaskRunRecord;
}
