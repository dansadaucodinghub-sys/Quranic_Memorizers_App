<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use InvalidArgumentException;
use Stringable;

final readonly class BackgroundWorkerIdentity implements Stringable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Background worker identity is invalid.');
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
