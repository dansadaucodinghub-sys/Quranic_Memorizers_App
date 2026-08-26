<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class TestSeed implements Seed
{
    /**
     * @param list<SeedId> $dependencies
     * @param list<SqlSeedStep> $steps
     */
    public function __construct(
        private SeedId $id,
        private string $description,
        private array $dependencies = [],
        private array $steps = [],
    ) {
    }

    public function id(): SeedId
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function steps(): array
    {
        return $this->steps;
    }
}
