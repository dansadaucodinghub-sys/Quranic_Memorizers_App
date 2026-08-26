<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use InvalidArgumentException;

final readonly class LogEventName
{
    public const MAX_LENGTH = 128;

    public function __construct(private string $value)
    {
        if (
            strlen($value) > self::MAX_LENGTH
            || preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Log event name is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
