<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\State;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationEventType;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedEventType;
use Qmdb\Shared\Schema\Seed\SeedRecord;
use Qmdb\Shared\Schema\Seed\SeedStatus;

interface SchemaStateRepository extends SchemaStateReader
{
    public function nextMigrationBatch(): int;

    public function startMigration(Migration $migration, string $checksum, int $batch): void;

    public function recordMigrationStep(
        Migration $migration,
        SqlMigrationStep $step,
        string $checksum,
        int $milliseconds,
    ): void;

    public function removeMigrationStep(Migration $migration, SqlMigrationStep $step): void;

    public function markMigration(
        Migration $migration,
        MigrationStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void;

    public function migrationEvent(
        Migration $migration,
        MigrationEventType $type,
        int $batch,
        ?string $stepId = null,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void;

    public function nextSeedBatch(): int;

    public function startSeed(Seed $seed, string $checksum, int $batch): void;

    public function markSeed(
        Seed $seed,
        SeedStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void;

    public function seedEvent(
        Seed $seed,
        SeedEventType $type,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void;
}
