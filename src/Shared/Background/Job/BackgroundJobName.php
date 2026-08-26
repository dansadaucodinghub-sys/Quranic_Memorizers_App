<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use InvalidArgumentException;
use Stringable;

final readonly class BackgroundJobName implements Stringable
{
    private const PATTERN = '/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/';

    public function __construct(private string $value)
    {
        if (strlen($value) > 120 || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Background job name is invalid.');
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
