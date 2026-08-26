<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

final readonly class SchemaMetadataDefinition
{
    public const LEDGER_VERSION = '1';

    /** @return list<string> */
    public static function tableNames(): array
    {
        return [
            'qmdb_schema_meta',
            'qmdb_schema_migrations',
            'qmdb_schema_migration_steps',
            'qmdb_schema_migration_events',
            'qmdb_schema_seeds',
            'qmdb_schema_seed_events',
        ];
    }

    /** @return array<string, list<string>> */
    public static function columns(): array
    {
        return [
            'qmdb_schema_meta' => ['meta_key', 'meta_value', 'updated_at'],
            'qmdb_schema_migrations' => [
                'migration_id',
                'description',
                'checksum',
                'status',
                'batch',
                'started_at',
                'completed_at',
                'execution_ms',
                'application_version',
                'failure_code',
            ],
            'qmdb_schema_migration_steps' => [
                'migration_id',
                'step_id',
                'step_sequence',
                'checksum',
                'applied_at',
                'execution_ms',
            ],
            'qmdb_schema_migration_events' => [
                'event_id',
                'migration_id',
                'step_id',
                'event_type',
                'batch',
                'failure_code',
                'execution_ms',
                'occurred_at',
            ],
            'qmdb_schema_seeds' => [
                'seed_id',
                'description',
                'checksum',
                'status',
                'batch',
                'started_at',
                'completed_at',
                'execution_ms',
                'application_version',
                'failure_code',
            ],
            'qmdb_schema_seed_events' => [
                'event_id',
                'seed_id',
                'step_id',
                'event_type',
                'batch',
                'failure_code',
                'execution_ms',
                'occurred_at',
            ],
        ];
    }

    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS qmdb_schema_meta (\n"
                . "  meta_key VARCHAR(64) NOT NULL,\n"
                . "  meta_value VARCHAR(255) NOT NULL,\n"
                . "  updated_at DATETIME(6) NOT NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_meta PRIMARY KEY (meta_key)\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            "CREATE TABLE IF NOT EXISTS qmdb_schema_migrations (\n"
                . "  migration_id VARCHAR(100) NOT NULL,\n"
                . "  description VARCHAR(200) NOT NULL,\n"
                . "  checksum BINARY(32) NOT NULL,\n"
                . "  status ENUM('RUNNING','PARTIAL','APPLIED','FAILED','ROLLING_BACK',"
                . "'ROLLED_BACK','ROLLBACK_FAILED','DRIFTED') NOT NULL,\n"
                . "  batch INT UNSIGNED NOT NULL,\n"
                . "  started_at DATETIME(6) NULL,\n"
                . "  completed_at DATETIME(6) NULL,\n"
                . "  execution_ms INT UNSIGNED NULL,\n"
                . "  application_version VARCHAR(64) NOT NULL,\n"
                . "  failure_code VARCHAR(64) NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_migrations PRIMARY KEY (migration_id),\n"
                . "  KEY idx_qmdb_schema_migrations_status_batch (status, batch)\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            "CREATE TABLE IF NOT EXISTS qmdb_schema_migration_steps (\n"
                . "  migration_id VARCHAR(100) NOT NULL,\n"
                . "  step_id VARCHAR(80) NOT NULL,\n"
                . "  step_sequence SMALLINT UNSIGNED NOT NULL,\n"
                . "  checksum BINARY(32) NOT NULL,\n"
                . "  applied_at DATETIME(6) NOT NULL,\n"
                . "  execution_ms INT UNSIGNED NOT NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_migration_steps PRIMARY KEY (migration_id, step_id),\n"
                . "  CONSTRAINT uq_qmdb_schema_migration_steps_sequence UNIQUE (migration_id, step_sequence),\n"
                . "  CONSTRAINT fk_qmdb_schema_migration_steps_migration FOREIGN KEY (migration_id)\n"
                . "    REFERENCES qmdb_schema_migrations (migration_id) ON DELETE RESTRICT\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            "CREATE TABLE IF NOT EXISTS qmdb_schema_migration_events (\n"
                . "  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                . "  migration_id VARCHAR(100) NOT NULL,\n"
                . "  step_id VARCHAR(80) NULL,\n"
                . "  event_type ENUM('DISCOVERED','STARTED','STEP_APPLIED','PARTIAL','APPLIED','FAILED',"
                . "'ROLLBACK_STARTED','STEP_ROLLED_BACK','ROLLED_BACK','ROLLBACK_FAILED','DRIFT_DETECTED') NOT NULL,\n"
                . "  batch INT UNSIGNED NOT NULL,\n"
                . "  failure_code VARCHAR(64) NULL,\n"
                . "  execution_ms INT UNSIGNED NULL,\n"
                . "  occurred_at DATETIME(6) NOT NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_migration_events PRIMARY KEY (event_id),\n"
                . "  KEY idx_qmdb_schema_migration_events_migration (migration_id, event_id)\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            "CREATE TABLE IF NOT EXISTS qmdb_schema_seeds (\n"
                . "  seed_id VARCHAR(100) NOT NULL,\n"
                . "  description VARCHAR(200) NOT NULL,\n"
                . "  checksum BINARY(32) NOT NULL,\n"
                . "  status ENUM('RUNNING','APPLIED','FAILED','DRIFTED') NOT NULL,\n"
                . "  batch INT UNSIGNED NOT NULL,\n"
                . "  started_at DATETIME(6) NULL,\n"
                . "  completed_at DATETIME(6) NULL,\n"
                . "  execution_ms INT UNSIGNED NULL,\n"
                . "  application_version VARCHAR(64) NOT NULL,\n"
                . "  failure_code VARCHAR(64) NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_seeds PRIMARY KEY (seed_id),\n"
                . "  KEY idx_qmdb_schema_seeds_status_batch (status, batch)\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            "CREATE TABLE IF NOT EXISTS qmdb_schema_seed_events (\n"
                . "  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                . "  seed_id VARCHAR(100) NOT NULL,\n"
                . "  step_id VARCHAR(80) NULL,\n"
                . "  event_type ENUM('DISCOVERED','STARTED','APPLIED','FAILED','DRIFT_DETECTED') NOT NULL,\n"
                . "  batch INT UNSIGNED NOT NULL,\n"
                . "  failure_code VARCHAR(64) NULL,\n"
                . "  execution_ms INT UNSIGNED NULL,\n"
                . "  occurred_at DATETIME(6) NOT NULL,\n"
                . "  CONSTRAINT pk_qmdb_schema_seed_events PRIMARY KEY (event_id),\n"
                . "  KEY idx_qmdb_schema_seed_events_seed (seed_id, event_id)\n"
                . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        ];
    }
}
