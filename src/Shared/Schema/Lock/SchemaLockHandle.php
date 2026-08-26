<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Lock;

use LogicException;

final class SchemaLockHandle
{
    private bool $released = false;

    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function markReleased(): void
    {
        if ($this->released) {
            throw new LogicException('Schema lock handle was already released.');
        }
        $this->released = true;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }
}
