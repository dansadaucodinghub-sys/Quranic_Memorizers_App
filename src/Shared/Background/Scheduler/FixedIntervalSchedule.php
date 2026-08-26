<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class FixedIntervalSchedule implements Schedule
{
    public function __construct(private int $intervalSeconds, private int $offsetSeconds = 0)
    {
        if ($intervalSeconds < 1 || $intervalSeconds > 31_536_000) {
            throw new InvalidArgumentException('Schedule interval is outside the safe range.');
        }
        if ($offsetSeconds < 0 || $offsetSeconds >= $intervalSeconds) {
            throw new InvalidArgumentException('Schedule offset is invalid.');
        }
    }

    public function currentDueTime(DateTimeImmutable $now): DateTimeImmutable
    {
        $timestamp = $now->getTimestamp();
        $slot = intdiv($timestamp - $this->offsetSeconds, $this->intervalSeconds)
            * $this->intervalSeconds + $this->offsetSeconds;

        return (new DateTimeImmutable('@' . $slot))->setTimezone(new DateTimeZone('UTC'));
    }

    public function nextTime(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->currentDueTime($now)->modify('+' . $this->intervalSeconds . ' seconds');
    }

    public function description(): string
    {
        $description = 'Every ' . $this->intervalSeconds . ' seconds';

        return $this->offsetSeconds === 0
            ? $description . ' from the UTC epoch.'
            : $description . ' with UTC offset ' . $this->offsetSeconds . ' seconds.';
    }
}
