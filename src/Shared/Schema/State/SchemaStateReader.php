<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\State;

use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Seed\SeedRecord;

interface SchemaStateReader
{
    /** @return array<string, MigrationRecord> */
    public function migrationRecords(): array;

    /** @return array<string, SeedRecord> */
    public function seedRecords(): array;
}
