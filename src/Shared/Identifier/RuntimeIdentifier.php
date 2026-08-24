<?php

declare(strict_types=1);

namespace Qmdb\Shared\Identifier;

use InvalidArgumentException;

/** Runtime-scoped opaque identifier; never a domain public-identifier strategy. */
final readonly class RuntimeIdentifier
{
    public const LENGTH = 32;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Runtime identifier is invalid.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
