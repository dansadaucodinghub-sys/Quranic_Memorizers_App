<?php

declare(strict_types=1);

namespace Qmdb\Shared\Module;

use Stringable;

final readonly class ModuleId implements Stringable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/', $value) !== 1) {
            throw new ModuleDependencyException('Module identifier is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
