<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ScheduledExecutionSlot
{
    private DateTimeImmutable $scheduledFor;

    public function __construct(private ScheduledTaskId $taskId, DateTimeImmutable $scheduledFor)
    {
        $utc = $scheduledFor->setTimezone(new DateTimeZone('UTC'));
        $this->scheduledFor = $utc->setTime(
            (int) $utc->format('H'),
            (int) $utc->format('i'),
            (int) $utc->format('s'),
            0,
        );
    }

    public function taskId(): ScheduledTaskId
    {
        return $this->taskId;
    }

    public function scheduledFor(): DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function equals(self $other): bool
    {
        return $this->taskId->value() === $other->taskId->value()
            && $this->scheduledFor == $other->scheduledFor;
    }
}
