<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use LogicException;

final class MigrationRegistryBuilder
{
    /** @var array<string, Migration> */
    private array $migrations = [];
    private bool $frozen = false;

    public function register(Migration $migration): self
    {
        if ($this->frozen) {
            throw new LogicException('Migration registry is frozen.');
        }
        $id = $migration->id()->value();
        if (isset($this->migrations[$id])) {
            throw new LogicException('Duplicate migration ID.');
        }
        $this->migrations[$id] = $migration;

        return $this;
    }

    public function build(): MigrationRegistry
    {
        $this->frozen = true;

        return new MigrationRegistry(array_values($this->migrations));
    }
}
