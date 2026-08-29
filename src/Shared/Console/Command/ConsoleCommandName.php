<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use InvalidArgumentException;
use Stringable;

final readonly class ConsoleCommandName implements Stringable
{
    private const PATTERN = '/\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)+\z/';

    public function __construct(private string $value)
    {
        if (strlen($value) > 80 || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Console command name is invalid.');
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
