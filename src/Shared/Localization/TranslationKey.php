<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

use InvalidArgumentException;

final readonly class TranslationKey
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 120 || preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $value) !== 1) {
            throw new InvalidArgumentException('Translation key is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
