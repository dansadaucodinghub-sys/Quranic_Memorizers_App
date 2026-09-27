<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-019: generic audit lineage, transactional outbox, and idempotency. */
final readonly class CreateAuditOutboxIdempotencyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260927102000_create_audit_outbox_idempotency');
    }

    public function description(): string
    {
        return 'Create cross-domain tamper-evident audit, outbox, and idempotency records.';
    }

    public function dependencies(): array
    {
        return [(new CreatePrivacySecurityOperationsMigration())->id()];
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
            $this->step('001_audit_events', 'Create generic append-only audit lineage.', <<<'SQL'
CREATE TABLE audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 stream_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, sequence_number BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_kind VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_public_id BINARY(16) NULL, subject_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, subject_public_id BINARY(16) NULL,
 outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 correlation_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 safe_metadata_json JSON NOT NULL, previous_hash BINARY(32) NULL, event_hash BINARY(32) NOT NULL, occurred_at DATETIME(6) NOT NULL, recorded_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_audit_event_public(public_id), UNIQUE KEY uq_p12_audit_stream_sequence(stream_code,sequence_number), UNIQUE KEY uq_p12_audit_event_hash(event_hash),
 KEY ix_p12_audit_workspace(workspace_id,recorded_at,id), KEY ix_p12_audit_subject(subject_kind,subject_public_id,recorded_at,id), KEY ix_p12_audit_correlation(correlation_id,id),
 CONSTRAINT fk_p12_audit_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_audit_event CHECK(sequence_number>=1 AND actor_kind IN ('ACCOUNT','SERVICE','SYSTEM','API_CLIENT') AND outcome_code IN ('SUCCEEDED','DENIED','FAILED','PARTIAL') AND JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('002_audit_checkpoints', 'Create externally publishable audit checkpoints.', <<<'SQL'
CREATE TABLE audit_checkpoints (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, stream_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 through_sequence BIGINT UNSIGNED NOT NULL, head_hash BINARY(32) NOT NULL, key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 signature VARBINARY(512) NOT NULL, published_reference_hash BINARY(32) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_audit_checkpoint_public(public_id), UNIQUE KEY uq_p12_audit_checkpoint_stream(stream_code,through_sequence),
 CONSTRAINT ck_p12_audit_checkpoint CHECK(through_sequence>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('003_audit_runs', 'Create append-only audit verification evidence.', <<<'SQL'
CREATE TABLE audit_verification_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, stream_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 from_sequence BIGINT UNSIGNED NOT NULL, through_sequence BIGINT UNSIGNED NOT NULL, examined_count BIGINT UNSIGNED NOT NULL,
 outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, first_invalid_sequence BIGINT UNSIGNED NULL,
 evidence_hash BINARY(32) NOT NULL, started_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_audit_run_public(public_id), KEY ix_p12_audit_run_stream(stream_code,completed_at,id),
 CONSTRAINT ck_p12_audit_run CHECK(from_sequence>=1 AND through_sequence>=from_sequence AND outcome_code IN ('VALID','INVALID','INCOMPLETE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('004_outbox', 'Create bounded transactional event publication records.', <<<'SQL'
CREATE TABLE outbox_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 aggregate_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_public_id BINARY(16) NOT NULL,
 aggregate_version INT UNSIGNED NOT NULL, event_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_json JSON NOT NULL, payload_hash BINARY(32) NOT NULL, correlation_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 priority_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, available_at DATETIME(6) NOT NULL, leased_until DATETIME(6) NULL,
 lease_token BINARY(16) NULL, published_at DATETIME(6) NULL, dead_lettered_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_outbox_public(public_id), UNIQUE KEY uq_p12_outbox_aggregate(aggregate_kind,aggregate_public_id,aggregate_version,event_code),
 KEY ix_p12_outbox_due(priority_code,status_code,available_at,id), KEY ix_p12_outbox_workspace(workspace_id,status_code,id),
 CONSTRAINT fk_p12_outbox_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_outbox CHECK(aggregate_version>=1 AND priority_code IN ('CRITICAL','HIGH','NORMAL','BULK') AND status_code IN ('PENDING','PROCESSING','PUBLISHED','RETRY','DEAD_LETTER') AND attempt_count<=20 AND JSON_VALID(payload_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('005_idempotency', 'Create scoped request idempotency receipts.', <<<'SQL'
CREATE TABLE idempotency_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 actor_kind VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operation_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, idempotency_key_hash BINARY(32) NOT NULL,
 request_hash BINARY(32) NOT NULL, response_status SMALLINT UNSIGNED NULL, response_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL, expires_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_idempotency_public(public_id), UNIQUE KEY uq_p12_idempotency_scope(actor_kind,actor_reference,operation_code,idempotency_key_hash),
 KEY ix_p12_idempotency_expiry(status_code,expires_at,id),
 CONSTRAINT fk_p12_idempotency_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_idempotency CHECK(actor_kind IN ('ACCOUNT','SERVICE','API_CLIENT') AND status_code IN ('PROCESSING','COMPLETED','FAILED') AND (response_status IS NULL OR response_status BETWEEN 100 AND 599))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('006_audit_update_trigger', 'Protect audit events from updates.', "CREATE TRIGGER trg_p12_audit_event_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit events are immutable'"),
            $this->step('007_audit_delete_trigger', 'Protect audit events from deletion.', "CREATE TRIGGER trg_p12_audit_event_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit events are immutable'"),
            $this->step('008_audit_run_update_trigger', 'Protect audit verification runs from updates.', "CREATE TRIGGER trg_p12_audit_run_no_update BEFORE UPDATE ON audit_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit verification runs are immutable'"),
            $this->step('009_audit_run_delete_trigger', 'Protect audit verification runs from deletion.', "CREATE TRIGGER trg_p12_audit_run_no_delete BEFORE DELETE ON audit_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit verification runs are immutable'"),
        ];
    }

    private function step(string $id, string $description, string $sql): SqlMigrationStep
    {
        return new SqlMigrationStep(new MigrationStepId($id), $description, $sql);
    }
}
