<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

interface Seed
{
    public function id(): SeedId;

    public function description(): string;

    /** @return list<SeedId> */
    public function dependencies(): array;

    /** @return list<SqlSeedStep> */
    public function steps(): array;
}
