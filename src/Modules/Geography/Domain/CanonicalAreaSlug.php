<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Domain;

use InvalidArgumentException;

final readonly class CanonicalAreaSlug
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $value) !== 1 || strlen($value) > 120) {
            throw new InvalidArgumentException('Administrative area slug is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
