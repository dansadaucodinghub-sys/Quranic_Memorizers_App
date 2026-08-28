<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use InvalidArgumentException;
use Stringable;

final readonly class PermissionCode implements Stringable
{
    public const int MAX_LENGTH = 128;

    public function __construct(private string $value)
    {
        if (
            strlen($value) > self::MAX_LENGTH
            || preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*){2,}\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Permission code is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
