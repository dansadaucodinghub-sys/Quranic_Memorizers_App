<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

use InvalidArgumentException;

final readonly class MemorizedJuzCount
{
    public function __construct(private int $value)
    {
        if ($value < 0 || $value > 30) {
            throw new InvalidArgumentException('Memorized Juz count is outside the approved range.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }
}
