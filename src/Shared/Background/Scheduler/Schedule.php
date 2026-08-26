<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;

interface Schedule
{
    public function currentDueTime(DateTimeImmutable $now): DateTimeImmutable;

    public function nextTime(DateTimeImmutable $now): DateTimeImmutable;

    public function description(): string;
}
