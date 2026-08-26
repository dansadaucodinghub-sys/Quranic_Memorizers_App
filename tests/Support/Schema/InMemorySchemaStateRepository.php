<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Schema;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationEventType;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedEventType;
use Qmdb\Shared\Schema\Seed\SeedRecord;
use Qmdb\Shared\Schema\Seed\SeedStatus;
use Qmdb\Shared\Schema\State\SchemaStateRepository;

final class InMemorySchemaStateRepository implements SchemaStateRepository
{
    /** @var array<string, MigrationRecord> */
    public array $migrations = [];
    /** @var array<string, SeedRecord> */
    public array $seeds = [];
    /** @var list<array{string, string, string|null}> */
    public array $migrationEvents = [];
    /** @var list<array{string, string}> */
    public array $seedEvents = [];

    public function migrationRecords(): array
    {
        return $this->migrations;
    }

    public function nextMigrationBatch(): int
    {
        $batches = array_map(static fn (MigrationRecord $record): int => $record->batch, $this->migrations);

        return ($batches === [] ? 0 : max($batches)) + 1;
    }

    public function startMigration(Migration $migration, string $checksum, int $batch): void
    {
        $existing = array_key_exists($migration->id()->value(), $this->migrations)
            ? $this->migrations[$migration->id()->value()]
            : null;
        $this->migrations[$migration->id()->value()] = new MigrationRecord(
            $migration->id()->value(),
            $existing === null ? $checksum : $existing->checksum,
            MigrationStatus::RUNNING,
            $batch,
            $existing === null ? [] : $existing->appliedStepChecksums,
        );
    }

    public function recordMigrationStep(
        Migration $migration,
        SqlMigrationStep $step,
        string $checksum,
        int $milliseconds,
    ): void {
        $record = $this->migrations[$migration->id()->value()];
        $steps = $record->appliedStepChecksums;
        $steps[$step->id()->value()] = $checksum;
        $this->migrations[$record->id] = new MigrationRecord(
            $record->id,
            $record->checksum,
            $record->status,
            $record->batch,
            $steps,
        );
    }

    public function removeMigrationStep(Migration $migration, SqlMigrationStep $step): void
    {
        $record = $this->migrations[$migration->id()->value()];
        $steps = $record->appliedStepChecksums;
        unset($steps[$step->id()->value()]);
        $this->migrations[$record->id] = new MigrationRecord(
            $record->id,
            $record->checksum,
            $record->status,
            $record->batch,
            $steps,
        );
    }

    public function markMigration(
        Migration $migration,
        MigrationStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $record = $this->migrations[$migration->id()->value()];
        $this->migrations[$record->id] = new MigrationRecord(
            $record->id,
            $record->checksum,
            $status,
            $batch,
            $record->appliedStepChecksums,
        );
    }

    public function migrationEvent(
        Migration $migration,
        MigrationEventType $type,
        int $batch,
        ?string $stepId = null,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $this->migrationEvents[] = [$migration->id()->value(), $type->value, $failureCode];
    }

    public function seedRecords(): array
    {
        return $this->seeds;
    }

    public function nextSeedBatch(): int
    {
        $batches = array_map(static fn (SeedRecord $record): int => $record->batch, $this->seeds);

        return ($batches === [] ? 0 : max($batches)) + 1;
    }

    public function startSeed(Seed $seed, string $checksum, int $batch): void
    {
        $existing = array_key_exists($seed->id()->value(), $this->seeds)
            ? $this->seeds[$seed->id()->value()]
            : null;
        $this->seeds[$seed->id()->value()] = new SeedRecord(
            $seed->id()->value(),
            $existing === null ? $checksum : $existing->checksum,
            SeedStatus::RUNNING,
            $batch,
        );
    }

    public function markSeed(
        Seed $seed,
        SeedStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $record = $this->seeds[$seed->id()->value()];
        $this->seeds[$record->id] = new SeedRecord($record->id, $record->checksum, $status, $batch);
    }

    public function seedEvent(
        Seed $seed,
        SeedEventType $type,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $this->seedEvents[] = [$seed->id()->value(), $type->value];
    }
}
