<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

use InvalidArgumentException;

final class PersonRegistryCode
{
    private const string PATTERN = '/\AQMP-[A-HJKMNP-TV-Z2-9]{16}\z/D';

    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Person registry code is invalid.');
        }
        $this->value = $value;
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
