<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateScheduledTaskRunsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260825000100_create_scheduled_task_runs');
    }

    public function description(): string
    {
        return 'Create the global scheduler execution ledger.';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_scheduled_task_runs'),
            'Create scheduler execution ledger.',
            <<<'SQL'
CREATE TABLE qmdb_scheduled_task_runs (
    task_id VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scheduled_for DATETIME(6) NOT NULL,
    execution_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('CLAIMED','RUNNING','SUCCEEDED','FAILED','ABANDONED') NOT NULL,
    attempt SMALLINT UNSIGNED NOT NULL,
    claimed_at DATETIME(6) NOT NULL,
    lease_expires_at DATETIME(6) NOT NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    failed_at DATETIME(6) NULL,
    duration_ms DECIMAL(16,3) UNSIGNED NULL,
    failure_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    version BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (task_id, scheduled_for),
    UNIQUE KEY uq_qmdb_scheduled_task_runs_execution_id (execution_id),
    KEY ix_qmdb_scheduled_task_runs_status_scheduled (status, scheduled_for),
    KEY ix_qmdb_scheduled_task_runs_status_lease (status, lease_expires_at),
    CONSTRAINT ck_qmdb_scheduled_task_runs_attempt CHECK (attempt >= 1),
    CONSTRAINT ck_qmdb_scheduled_task_runs_version CHECK (version >= 1),
    CONSTRAINT ck_qmdb_scheduled_task_runs_lease CHECK (lease_expires_at >= claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_scheduled_task_runs'),
            'Drop scheduler execution ledger.',
            'DROP TABLE qmdb_scheduled_task_runs',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
