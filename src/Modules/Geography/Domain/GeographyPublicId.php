<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

use InvalidArgumentException;

final readonly class GeographyPublicId
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower($value);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Geography public identifier is invalid.');
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toBinary(): string
    {
        $binary = hex2bin(str_replace('-', '', $this->value));
        if (!is_string($binary)) {
            throw new InvalidArgumentException('Geography public identifier cannot be encoded.');
        }

        return $binary;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return array{type: string} */
    public function __debugInfo(): array
    {
        return ['type' => 'geography-public-id'];
    }
}
