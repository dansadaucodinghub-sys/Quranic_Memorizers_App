<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use DateTimeImmutable;
use Qmdb\Shared\Time\Clock;
use UnderflowException;

final class SequenceClock implements Clock
{
    /** @param list<DateTimeImmutable> $values */
    public function __construct(private array $values)
    {
    }

    public function now(): DateTimeImmutable
    {
        return array_shift($this->values)
            ?? throw new UnderflowException('No clock value remains.');
    }
}
