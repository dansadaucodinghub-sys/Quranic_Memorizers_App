<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

use LogicException;

final class SeedRegistryBuilder
{
    /** @var array<string, Seed> */
    private array $seeds = [];
    private bool $frozen = false;

    public function register(Seed $seed): self
    {
        if ($this->frozen) {
            throw new LogicException('Seed registry is frozen.');
        }
        $id = $seed->id()->value();
        if (isset($this->seeds[$id])) {
            throw new LogicException('Duplicate seed ID.');
        }
        $this->seeds[$id] = $seed;

        return $this;
    }

    public function build(): SeedRegistry
    {
        $this->frozen = true;

        return new SeedRegistry(array_values($this->seeds));
    }
}
