<?php

declare(strict_types=1);

namespace Qmdb\Shared\Time;

final readonly class SystemMonotonicClock implements MonotonicClock
{
    public function nanoseconds(): int
    {
        return hrtime(true);
    }
}
