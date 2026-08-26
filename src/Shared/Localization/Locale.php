<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

use InvalidArgumentException;

final readonly class Locale
{
    public function __construct(private string $value)
    {
        if (!in_array($value, ['en', 'ar'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function direction(): TextDirection
    {
        return $this->value === 'ar' ? TextDirection::RTL : TextDirection::LTR;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
