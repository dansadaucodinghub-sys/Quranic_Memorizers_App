<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Durable projection state, immutable snapshots, and replay-safe P7 operations. */
final readonly class CreateCompetitionLiveProjectionsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912141000_create_competition_live_projections');
    }

    public function description(): string
    {
        return 'Create P7 live projection streams, immutable snapshots, offsets, and operation records.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionLiveOperationsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_projection_streams'), 'Create live projection stream cursors.', "CREATE TABLE competition_live_projection_streams (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, live_session_id BIGINT UNSIGNED NOT NULL, stream_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE', head_event_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, projected_event_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, snapshot_version INT UNSIGNED NOT NULL DEFAULT 1, current_snapshot_sha256 BINARY(32) NULL, transport_generation INT UNSIGNED NOT NULL DEFAULT 1, last_projected_at DATETIME(6) NULL, paused_at DATETIME(6) NULL, recovery_started_at DATETIME(6) NULL, recovered_at DATETIME(6) NULL, closed_at DATETIME(6) NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_projection_stream_public(public_id), UNIQUE KEY uq_p7_projection_stream_code(workspace_id,live_session_id,stream_code), UNIQUE KEY uq_p7_projection_stream_workspace_id(workspace_id,id), KEY ix_p7_projection_stream_status(workspace_id,status,updated_at,id), KEY ix_p7_projection_stream_backlog(status,projected_event_sequence,head_event_sequence,id), CONSTRAINT fk_p7_projection_stream_session FOREIGN KEY(workspace_id,live_session_id) REFERENCES competition_live_sessions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_projection_stream CHECK(status IN ('ACTIVE','PAUSED','RECOVERING','DEGRADED','CLOSED') AND projected_event_sequence<=head_event_sequence AND snapshot_version>=1 AND transport_generation>=1 AND version>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('002_projection_snapshots'), 'Create immutable public-safe snapshots.', "CREATE TABLE competition_live_projection_snapshots (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, projection_stream_id BIGINT UNSIGNED NOT NULL, event_sequence BIGINT UNSIGNED NOT NULL, snapshot_schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1, public_payload_canonical_json JSON NOT NULL, snapshot_sha256 BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_projection_snapshot_public(public_id), UNIQUE KEY uq_p7_projection_snapshot_sequence(workspace_id,projection_stream_id,event_sequence), KEY ix_p7_projection_snapshot_stream(workspace_id,projection_stream_id,event_sequence,id), CONSTRAINT fk_p7_projection_snapshot_stream FOREIGN KEY(workspace_id,projection_stream_id) REFERENCES competition_live_projection_streams(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_projection_snapshot CHECK(event_sequence>=0 AND snapshot_schema_version>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('003_delivery_offsets'), 'Create trusted-consumer offset leases.', "CREATE TABLE competition_live_delivery_offsets (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, projection_stream_id BIGINT UNSIGNED NOT NULL, consumer_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, consumer_group VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, last_acknowledged_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, lease_owner VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL, lease_expires_at DATETIME(6) NULL, version INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_delivery_offset_public(public_id), UNIQUE KEY uq_p7_delivery_offset_consumer(workspace_id,projection_stream_id,consumer_code,consumer_group), CONSTRAINT fk_p7_delivery_offset_stream FOREIGN KEY(workspace_id,projection_stream_id) REFERENCES competition_live_projection_streams(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_delivery_offset CHECK(last_acknowledged_sequence>=0 AND version>=1 AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('004_live_operations'), 'Create replay-safe authoritative P7 operation receipts.', "CREATE TABLE competition_live_operations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, submission_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_fingerprint BINARY(32) NOT NULL, aggregate_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_public_id BINARY(16) NOT NULL, result_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version_after INT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_live_operation_public(public_id), UNIQUE KEY uq_p7_live_operation_submission(submission_id), KEY ix_p7_live_operation_workspace(workspace_id,occurred_at,id), CONSTRAINT fk_p7_live_operation_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_live_operation CHECK(version_after>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('005_snapshot_no_update'), 'Prevent snapshot modification.', "CREATE TRIGGER trg_p7_projection_snapshots_no_update BEFORE UPDATE ON competition_live_projection_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Projection snapshots are immutable'"),
            new SqlMigrationStep(new MigrationStepId('006_snapshot_no_delete'), 'Prevent snapshot deletion.', "CREATE TRIGGER trg_p7_projection_snapshots_no_delete BEFORE DELETE ON competition_live_projection_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Projection snapshots are immutable'"),
        ];
    }

    public function down(): array
    {
        return [];
    }

    public function reversible(): bool
    {
        return false;
    }
}
