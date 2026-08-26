<?php

declare(strict_types=1);

namespace Qmdb\Shared\Domain\Event;

use InvalidArgumentException;
use Stringable;

final readonly class DomainEventName implements Stringable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/', $value) !== 1) {
            throw new InvalidArgumentException('Domain event name is invalid.');
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
