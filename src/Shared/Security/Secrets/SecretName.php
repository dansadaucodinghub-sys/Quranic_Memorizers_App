<?php

declare(strict_types=1);

namespace Qmdb\Shared\Security\Secrets;

use InvalidArgumentException;

final readonly class SecretName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\A[A-Z][A-Z0-9_]*\z/', $value) !== 1) {
            throw new InvalidArgumentException('Secret name is invalid.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
