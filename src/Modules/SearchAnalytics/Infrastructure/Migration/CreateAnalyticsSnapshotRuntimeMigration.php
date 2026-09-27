<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-016: deterministic, rebuildable metric snapshots. */
final readonly class CreateAnalyticsSnapshotRuntimeMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260925102000_create_analytics_snapshot_runtime');
    }

    public function description(): string
    {
        return 'Create bounded snapshot jobs, immutable completed values, and append-only events.';
    }

    public function dependencies(): array
    {
        return [(new CreateAnalyticsCatalogMigration())->id()];
    }

    public function reversible(): bool
    {
        return false;
    }

    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_runs'), 'Create snapshot run ledger.', <<<'SQL'
CREATE TABLE analytics_snapshot_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 metric_definition_id BIGINT UNSIGNED NOT NULL, period_start DATETIME(6) NOT NULL, period_end DATETIME(6) NOT NULL,
 source_watermark VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, next_attempt_at DATETIME(6) NOT NULL, lease_owner BINARY(16) NULL,
 lease_expires_at DATETIME(6) NULL, completed_at DATETIME(6) NULL, failed_at DATETIME(6) NULL,
 error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, checksum BINARY(32) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_snapshot_public(public_id),
 UNIQUE KEY uq_p11_snapshot_identity(metric_definition_id,workspace_id,period_start,period_end,source_watermark),
 KEY ix_p11_snapshot_due(status_code,next_attempt_at,id), KEY ix_p11_snapshot_workspace(workspace_id,status_code,period_end,id),
 CONSTRAINT fk_p11_snapshot_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_snapshot_metric FOREIGN KEY(metric_definition_id) REFERENCES analytics_metric_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_snapshot_run CHECK(status_code IN ('PENDING','LEASED','RETRY','COMPLETED','FAILED','STALE') AND period_end>period_start AND attempt_count<=10 AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_values'), 'Create minimized snapshot values.', <<<'SQL'
CREATE TABLE analytics_snapshot_values (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, snapshot_run_id BIGINT UNSIGNED NOT NULL,
 dimension_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, dimension_json JSON NOT NULL,
 raw_count BIGINT UNSIGNED NOT NULL, disclosed_value BIGINT UNSIGNED NULL, suppression_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 checksum BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_snapshot_value(snapshot_run_id,dimension_key),
 KEY ix_p11_snapshot_value_lookup(dimension_key,disclosed_value),
 CONSTRAINT fk_p11_snapshot_value_run FOREIGN KEY(snapshot_run_id) REFERENCES analytics_snapshot_runs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_snapshot_value CHECK(JSON_VALID(dimension_json) AND suppression_code IN ('SMALL_GROUP','PRIVATE_SCOPE','DIFFERENCING_RISK') OR suppression_code IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_events'), 'Create append-only snapshot events.', <<<'SQL'
CREATE TABLE analytics_snapshot_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, snapshot_run_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_metadata_json JSON NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_snapshot_event_public(public_id), KEY ix_p11_snapshot_event_run(snapshot_run_id,id),
 CONSTRAINT fk_p11_snapshot_event_run FOREIGN KEY(snapshot_run_id) REFERENCES analytics_snapshot_runs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_snapshot_event CHECK(event_code IN ('CREATED','CLAIMED','COMPLETED','RETRY_SCHEDULED','FAILED','STALE') AND JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_completed_update'), 'Reject changes to completed snapshots.', <<<'SQL'
CREATE TRIGGER trg_p11_snapshot_completed_no_update BEFORE UPDATE ON analytics_snapshot_runs
FOR EACH ROW SET NEW.status_code=IF(OLD.status_code='COMPLETED','IMMUTABLE',NEW.status_code)
SQL),
            new SqlMigrationStep(new MigrationStepId('005_value_update'), 'Reject snapshot value changes.', <<<'SQL'
CREATE TRIGGER trg_p11_snapshot_value_no_update BEFORE UPDATE ON analytics_snapshot_values
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Analytics snapshot values are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('006_value_delete'), 'Reject snapshot value deletion.', <<<'SQL'
CREATE TRIGGER trg_p11_snapshot_value_no_delete BEFORE DELETE ON analytics_snapshot_values
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Analytics snapshot values are immutable'
SQL),
        ];
    }
}
