<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use InvalidArgumentException;
use Stringable;

final readonly class ScheduledTaskId implements Stringable
{
    public function __construct(private string $value)
    {
        if (
            strlen($value) > 120
            || preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Scheduled task ID is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
