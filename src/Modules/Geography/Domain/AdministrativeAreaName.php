<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

use InvalidArgumentException;

final readonly class AdministrativeAreaName
{
    public function __construct(private string $value)
    {
        if (
            $value === '' || strlen($value) > 191 || !mb_check_encoding($value, 'UTF-8')
            || str_contains($value, "\0") || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
            || str_contains($value, '<') || str_contains($value, '>')
        ) {
            throw new InvalidArgumentException('Administrative area name is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
