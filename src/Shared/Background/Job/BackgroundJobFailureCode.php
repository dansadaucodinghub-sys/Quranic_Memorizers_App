<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use InvalidArgumentException;
use Stringable;

final readonly class BackgroundJobFailureCode implements Stringable
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 64 || preg_match('/\A[A-Z][A-Z0-9_]+\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Background job failure code is invalid.');
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
