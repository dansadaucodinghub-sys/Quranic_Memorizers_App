<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Time\MonotonicClock;
use UnderflowException;

final class FakeMonotonicClock implements MonotonicClock
{
    /** @param list<int> $values */
    public function __construct(private array $values)
    {
    }

    public function nanoseconds(): int
    {
        return array_shift($this->values)
            ?? throw new UnderflowException('No monotonic clock value remains.');
    }
}
