<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

use PDO;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\State\PdoResultReader;
use Throwable;

final readonly class SchemaMetadataVerifier implements SchemaMetadataInspection
{
    public function __construct(private SchemaConnectionProvider $provider)
    {
    }

    public function verify(): SchemaMetadataReport
    {
        try {
            $connection = $this->provider->connection();
            $statement = $connection->prepare(
                'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
                . implode(', ', array_fill(0, 6, '?')) . ') ORDER BY TABLE_NAME',
            );
            $statement->execute(SchemaMetadataDefinition::tableNames());
            $rows = PdoResultReader::rows($statement);
            if (count($rows) === 0) {
                return new SchemaMetadataReport(SchemaMetadataStatus::NOT_INSTALLED, 'SCHEMA_NOT_INSTALLED');
            }
            if (count($rows) !== 6) {
                return new SchemaMetadataReport(SchemaMetadataStatus::INVALID, 'SCHEMA_METADATA_INCOMPLETE');
            }
            foreach ($rows as $row) {
                if (
                    PdoResultReader::string($row, 'ENGINE') !== 'InnoDB'
                    || !str_starts_with(PdoResultReader::string($row, 'TABLE_COLLATION'), 'utf8mb4_')
                ) {
                    return new SchemaMetadataReport(SchemaMetadataStatus::INVALID, 'SCHEMA_METADATA_INVALID');
                }
            }
            $columnStatement = $connection->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
            );
            $primaryStatement = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS '
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_TYPE = 'PRIMARY KEY'",
            );
            foreach (SchemaMetadataDefinition::columns() as $tableName => $expectedColumns) {
                $columnStatement->execute([':table_name' => $tableName]);
                $actualColumns = array_map(
                    static fn (array $row): string => PdoResultReader::string($row, 'COLUMN_NAME'),
                    PdoResultReader::rows($columnStatement),
                );
                if ($actualColumns !== $expectedColumns) {
                    return new SchemaMetadataReport(SchemaMetadataStatus::INVALID, 'SCHEMA_METADATA_INVALID');
                }
                $primaryStatement->execute([':table_name' => $tableName]);
                if ((int) $primaryStatement->fetchColumn() !== 1) {
                    return new SchemaMetadataReport(SchemaMetadataStatus::INVALID, 'SCHEMA_METADATA_INVALID');
                }
            }
            $version = $connection->prepare(
                'SELECT meta_value FROM qmdb_schema_meta WHERE meta_key = :meta_key',
            );
            $version->execute([':meta_key' => 'ledger_version']);
            $value = $version->fetchColumn();
            if ($value === false) {
                return new SchemaMetadataReport(SchemaMetadataStatus::INVALID, 'SCHEMA_LEDGER_VERSION_MISSING');
            }
            if ($value !== SchemaMetadataDefinition::LEDGER_VERSION) {
                return new SchemaMetadataReport(SchemaMetadataStatus::INCOMPATIBLE, 'SCHEMA_LEDGER_INCOMPATIBLE');
            }

            return new SchemaMetadataReport(SchemaMetadataStatus::READY, 'SCHEMA_METADATA_READY');
        } catch (Throwable) {
            return new SchemaMetadataReport(SchemaMetadataStatus::UNAVAILABLE, 'SCHEMA_METADATA_UNAVAILABLE');
        }
    }
}
