<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use InvalidArgumentException;
use Stringable;

final readonly class MigrationStepId implements Stringable
{
    private const PATTERN = '/\A[0-9]{3}_[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/';

    public function __construct(private string $value)
    {
        if (strlen($value) > 80 || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Migration step ID is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function sequence(): int
    {
        return (int) substr($this->value, 0, 3);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
