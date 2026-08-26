<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Correlation;

use InvalidArgumentException;

final readonly class CorrelationId
{
    public const LENGTH = 32;

    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Correlation identifier is invalid.');
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
