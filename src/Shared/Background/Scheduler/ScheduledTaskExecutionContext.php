<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class ScheduledTaskExecutionContext
{
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $leaseExpiresAt;

    public function __construct(
        private ScheduledExecutionSlot $slot,
        private string $executionId,
        private CorrelationId $correlationId,
        private int $attempt,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $leaseExpiresAt,
    ) {
        $this->startedAt = UtcDateTime::normalize($startedAt);
        $this->leaseExpiresAt = UtcDateTime::normalize($leaseExpiresAt);
    }

    public function taskId(): ScheduledTaskId
    {
        return $this->slot->taskId();
    }

    public function slot(): ScheduledExecutionSlot
    {
        return $this->slot;
    }

    public function executionId(): string
    {
        return $this->executionId;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function leaseExpiresAt(): DateTimeImmutable
    {
        return $this->leaseExpiresAt;
    }
}
