<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

use InvalidArgumentException;
use Stringable;

final readonly class SeedStepId implements Stringable
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 80 || preg_match('/\A[0-9]{3}_[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $value) !== 1) {
            throw new InvalidArgumentException('Seed step ID is invalid.');
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
