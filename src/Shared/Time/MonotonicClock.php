<?php

declare(strict_types=1);

namespace Qmdb\Shared\Time;

interface MonotonicClock
{
    public function nanoseconds(): int;
}
