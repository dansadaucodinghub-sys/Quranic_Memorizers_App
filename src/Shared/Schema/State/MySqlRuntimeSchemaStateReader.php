<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\State;

use PDO;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Seed\SeedRecord;
use Qmdb\Shared\Schema\Seed\SeedStatus;

final readonly class MySqlRuntimeSchemaStateReader implements SchemaStateReader
{
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
        $steps = $this->provider->connection()->prepare(
            'SELECT step_id, checksum FROM qmdb_schema_migration_steps '
            . 'WHERE migration_id = :migration_id ORDER BY step_sequence',
        );
        foreach ($rows as $row) {
            $id = PdoResultReader::string($row, 'migration_id');
            $steps->execute([':migration_id' => $id]);
            $checksums = [];
            foreach (PdoResultReader::rows($steps) as $step) {
                $checksums[PdoResultReader::string($step, 'step_id')] = PdoResultReader::string($step, 'checksum');
            }
            $records[$id] = new MigrationRecord(
                $id,
                PdoResultReader::string($row, 'checksum'),
                MigrationStatus::from(PdoResultReader::string($row, 'status')),
                PdoResultReader::integer($row, 'batch'),
                $checksums,
            );
        }

        return $records;
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
}
