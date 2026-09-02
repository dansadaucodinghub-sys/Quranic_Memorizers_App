<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\State;

use PDO;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationEventType;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedEventType;
use Qmdb\Shared\Schema\Seed\SeedRecord;
use Qmdb\Shared\Schema\Seed\SeedStatus;

final readonly class MySqlSchemaStateRepository implements SchemaStateRepository
{
    private const APPLICATION_VERSION = '0.1.0-dev';

    public function __construct(private SchemaConnectionProvider $provider)
    {
    }

    public function migrationRecords(): array
    {
        $records = [];
        $rows = PdoResultReader::queryRows(
            $this->provider->connection(),
            'SELECT migration_id, checksum, status, batch FROM qmdb_schema_migrations ORDER BY migration_id',
        );
        $stepStatement = $this->provider->connection()->prepare(
            'SELECT step_id, checksum FROM qmdb_schema_migration_steps '
            . 'WHERE migration_id = :migration_id ORDER BY step_sequence',
        );
        foreach ($rows as $row) {
            $id = PdoResultReader::string($row, 'migration_id');
            $stepStatement->execute([':migration_id' => $id]);
            $steps = [];
            foreach (PdoResultReader::rows($stepStatement) as $step) {
                $steps[PdoResultReader::string($step, 'step_id')] = PdoResultReader::string($step, 'checksum');
            }
            $records[$id] = new MigrationRecord(
                $id,
                PdoResultReader::string($row, 'checksum'),
                MigrationStatus::from(PdoResultReader::string($row, 'status')),
                PdoResultReader::integer($row, 'batch'),
                $steps,
            );
        }

        return $records;
    }

    public function nextMigrationBatch(): int
    {
        return PdoResultReader::scalarInteger(
            $this->provider->connection(),
            'SELECT COALESCE(MAX(batch), 0) + 1 FROM qmdb_schema_migrations',
        );
    }

    public function startMigration(Migration $migration, string $checksum, int $batch): void
    {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO qmdb_schema_migrations '
            . '(migration_id, description, checksum, status, batch, started_at, completed_at, execution_ms, '
            . 'application_version, failure_code) VALUES '
            . '(:id, :description, :checksum, :status, :batch, UTC_TIMESTAMP(6), NULL, NULL, :version, NULL) '
            . 'ON DUPLICATE KEY UPDATE description = VALUES(description), checksum = VALUES(checksum), status = VALUES(status), batch = VALUES(batch), '
            . 'started_at = VALUES(started_at), completed_at = NULL, execution_ms = NULL, failure_code = NULL',
        );
        $statement->execute([
            ':id' => $migration->id()->value(),
            ':description' => $migration->description(),
            ':checksum' => $checksum,
            ':status' => MigrationStatus::RUNNING->value,
            ':batch' => $batch,
            ':version' => self::APPLICATION_VERSION,
        ]);
    }

    public function recordMigrationStep(
        Migration $migration,
        SqlMigrationStep $step,
        string $checksum,
        int $milliseconds,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO qmdb_schema_migration_steps '
            . '(migration_id, step_id, step_sequence, checksum, applied_at, execution_ms) '
            . 'VALUES (:migration_id, :step_id, :step_sequence, :checksum, UTC_TIMESTAMP(6), :execution_ms)',
        );
        $statement->execute([
            ':migration_id' => $migration->id()->value(),
            ':step_id' => $step->id()->value(),
            ':step_sequence' => $step->id()->sequence(),
            ':checksum' => $checksum,
            ':execution_ms' => $milliseconds,
        ]);
    }

    public function removeMigrationStep(Migration $migration, SqlMigrationStep $step): void
    {
        $statement = $this->provider->connection()->prepare(
            'DELETE FROM qmdb_schema_migration_steps WHERE migration_id = :migration_id AND step_id = :step_id',
        );
        $statement->execute([
            ':migration_id' => $migration->id()->value(),
            ':step_id' => $step->id()->value(),
        ]);
    }

    public function markMigration(
        Migration $migration,
        MigrationStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'UPDATE qmdb_schema_migrations SET status = :status, batch = :batch, '
            . 'completed_at = :completed_at, execution_ms = :execution_ms, failure_code = :failure_code '
            . 'WHERE migration_id = :migration_id',
        );
        $statement->execute([
            ':status' => $status->value,
            ':batch' => $batch,
            ':completed_at' => in_array($status, [MigrationStatus::RUNNING, MigrationStatus::ROLLING_BACK], true)
                ? null : gmdate('Y-m-d H:i:s.u'),
            ':execution_ms' => $milliseconds,
            ':failure_code' => $failureCode,
            ':migration_id' => $migration->id()->value(),
        ]);
    }

    public function migrationEvent(
        Migration $migration,
        MigrationEventType $type,
        int $batch,
        ?string $stepId = null,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO qmdb_schema_migration_events '
            . '(migration_id, step_id, event_type, batch, failure_code, execution_ms, occurred_at) '
            . 'VALUES (:migration_id, :step_id, :event_type, :batch, :failure_code, :execution_ms, UTC_TIMESTAMP(6))',
        );
        $statement->execute([
            ':migration_id' => $migration->id()->value(),
            ':step_id' => $stepId,
            ':event_type' => $type->value,
            ':batch' => $batch,
            ':failure_code' => $failureCode,
            ':execution_ms' => $milliseconds,
        ]);
    }

    public function seedRecords(): array
    {
        $records = [];
        $rows = PdoResultReader::queryRows(
            $this->provider->connection(),
            'SELECT seed_id, checksum, status, batch FROM qmdb_schema_seeds ORDER BY seed_id',
        );
        foreach ($rows as $row) {
            $id = PdoResultReader::string($row, 'seed_id');
            $records[$id] = new SeedRecord(
                $id,
                PdoResultReader::string($row, 'checksum'),
                SeedStatus::from(PdoResultReader::string($row, 'status')),
                PdoResultReader::integer($row, 'batch'),
            );
        }

        return $records;
    }

    public function nextSeedBatch(): int
    {
        return PdoResultReader::scalarInteger(
            $this->provider->connection(),
            'SELECT COALESCE(MAX(batch), 0) + 1 FROM qmdb_schema_seeds',
        );
    }

    public function startSeed(Seed $seed, string $checksum, int $batch): void
    {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO qmdb_schema_seeds '
            . '(seed_id, description, checksum, status, batch, started_at, completed_at, execution_ms, '
            . 'application_version, failure_code) VALUES '
            . '(:id, :description, :checksum, :status, :batch, UTC_TIMESTAMP(6), NULL, NULL, :version, NULL) '
            . 'ON DUPLICATE KEY UPDATE description = VALUES(description), checksum = VALUES(checksum), status = VALUES(status), batch = VALUES(batch), '
            . 'started_at = VALUES(started_at), completed_at = NULL, execution_ms = NULL, failure_code = NULL',
        );
        $statement->execute([
            ':id' => $seed->id()->value(),
            ':description' => $seed->description(),
            ':checksum' => $checksum,
            ':status' => SeedStatus::RUNNING->value,
            ':batch' => $batch,
            ':version' => self::APPLICATION_VERSION,
        ]);
    }

    public function markSeed(
        Seed $seed,
        SeedStatus $status,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'UPDATE qmdb_schema_seeds SET status = :status, batch = :batch, completed_at = UTC_TIMESTAMP(6), '
            . 'execution_ms = :execution_ms, failure_code = :failure_code WHERE seed_id = :seed_id',
        );
        $statement->execute([
            ':status' => $status->value,
            ':batch' => $batch,
            ':execution_ms' => $milliseconds,
            ':failure_code' => $failureCode,
            ':seed_id' => $seed->id()->value(),
        ]);
    }

    public function seedEvent(
        Seed $seed,
        SeedEventType $type,
        int $batch,
        ?string $failureCode = null,
        ?int $milliseconds = null,
    ): void {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO qmdb_schema_seed_events '
            . '(seed_id, step_id, event_type, batch, failure_code, execution_ms, occurred_at) '
            . 'VALUES (:seed_id, NULL, :event_type, :batch, :failure_code, :execution_ms, UTC_TIMESTAMP(6))',
        );
        $statement->execute([
            ':seed_id' => $seed->id()->value(),
            ':event_type' => $type->value,
            ':batch' => $batch,
            ':failure_code' => $failureCode,
            ':execution_ms' => $milliseconds,
        ]);
    }
}
