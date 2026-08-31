<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

use InvalidArgumentException;

final readonly class AdministrativeAreaCode
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[A-Z0-9][A-Z0-9-]{1,62}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Administrative area code is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
