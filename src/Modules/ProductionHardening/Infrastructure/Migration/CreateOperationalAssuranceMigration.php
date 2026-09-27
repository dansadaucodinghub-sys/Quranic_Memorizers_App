<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Approved P12 hardening extension for observability, incidents, key rotation, and recovery evidence. */
final readonly class CreateOperationalAssuranceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260927103000_create_operational_assurance');
    }

    public function description(): string
    {
        return 'Create service, SLI, alert, incident, key-rotation, backup, and restore assurance records.';
    }

    public function dependencies(): array
    {
        return [(new CreateAuditOutboxIdempotencyMigration())->id()];
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
            $this->step('001_service_catalog', 'Create critical service and dependency catalog.', <<<'SQL'
CREATE TABLE operational_service_catalog (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, service_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 criticality_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, recovery_order SMALLINT UNSIGNED NOT NULL,
 owner_role_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, dependency_codes_json JSON NOT NULL,
 degradation_mode VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, health_probe_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_service_public(public_id), UNIQUE KEY uq_p12_service_code(service_code), UNIQUE KEY uq_p12_recovery_order(recovery_order),
 CONSTRAINT ck_p12_service CHECK(criticality_code IN ('TIER_0','TIER_1','TIER_2','TIER_3') AND recovery_order>=1 AND status_code IN ('ACTIVE','RETIRED') AND version>=1 AND JSON_VALID(dependency_codes_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('002_sli_catalog', 'Create parameterized SLI catalog.', <<<'SQL'
CREATE TABLE operational_sli_definitions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, service_id BIGINT UNSIGNED NOT NULL,
 sli_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, metric_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 comparator_code VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, target_value DECIMAL(18,6) NOT NULL,
 window_seconds INT UNSIGNED NOT NULL, unit_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_sli_public(public_id), UNIQUE KEY uq_p12_sli_code(sli_code,version),
 CONSTRAINT fk_p12_sli_service FOREIGN KEY(service_id) REFERENCES operational_service_catalog(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_sli CHECK(comparator_code IN ('LT','LTE','GT','GTE') AND target_value>=0 AND window_seconds BETWEEN 60 AND 2592000 AND status_code IN ('ACTIVE','RETIRED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('003_metric_samples', 'Create bounded operational metric samples without user labels.', <<<'SQL'
CREATE TABLE operational_metric_samples (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, sli_definition_id BIGINT UNSIGNED NOT NULL,
 value_decimal DECIMAL(18,6) NOT NULL, sample_count INT UNSIGNED NOT NULL, correlation_hash BINARY(32) NULL,
 observed_at DATETIME(6) NOT NULL, expires_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_metric_sample_public(public_id), KEY ix_p12_metric_sample_sli(sli_definition_id,observed_at,id), KEY ix_p12_metric_sample_expiry(expires_at,id),
 CONSTRAINT fk_p12_metric_sample_sli FOREIGN KEY(sli_definition_id) REFERENCES operational_sli_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_metric_sample CHECK(sample_count>=1 AND expires_at>observed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('004_alert_intents', 'Create deduplicated owned alert intents.', <<<'SQL'
CREATE TABLE operational_alert_intents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, service_id BIGINT UNSIGNED NOT NULL,
 alert_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, severity_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 deduplication_key BINARY(32) NOT NULL, owner_role_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 runbook_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_context_json JSON NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 acknowledged_at DATETIME(6) NULL, resolved_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_alert_public(public_id), UNIQUE KEY uq_p12_alert_dedupe(deduplication_key), KEY ix_p12_alert_open(status_code,severity_code,created_at,id),
 CONSTRAINT fk_p12_alert_service FOREIGN KEY(service_id) REFERENCES operational_service_catalog(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_alert CHECK(severity_code IN ('INFO','WARNING','HIGH','CRITICAL') AND status_code IN ('OPEN','ACKNOWLEDGED','RESOLVED','SUPPRESSED') AND JSON_VALID(safe_context_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('005_incidents', 'Create controlled security and privacy incident cases.', <<<'SQL'
CREATE TABLE security_incidents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 incident_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, incident_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 severity_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 commander_account_id BIGINT UNSIGNED NOT NULL, summary VARCHAR(500) NOT NULL, evidence_manifest_hash BINARY(32) NULL,
 notification_decision_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 detected_at DATETIME(6) NOT NULL, contained_at DATETIME(6) NULL, recovered_at DATETIME(6) NULL, closed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_incident_public(public_id), UNIQUE KEY uq_p12_incident_code(incident_code), KEY ix_p12_incident_open(status_code,severity_code,detected_at,id),
 CONSTRAINT fk_p12_incident_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_incident_commander FOREIGN KEY(commander_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_incident CHECK(incident_type IN ('SECURITY','PRIVACY','SAFETY','AVAILABILITY') AND severity_code IN ('LOW','MEDIUM','HIGH','CRITICAL') AND status_code IN ('DETECTED','TRIAGED','CONTAINING','CONTAINED','RECOVERING','RECOVERED','POST_REVIEW','CLOSED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('006_incident_events', 'Create immutable incident timeline events.', <<<'SQL'
CREATE TABLE incident_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, incident_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NULL, event_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 safe_metadata_json JSON NOT NULL, evidence_hash BINARY(32) NULL, occurred_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_incident_event_public(public_id), KEY ix_p12_incident_event_case(incident_id,id),
 CONSTRAINT fk_p12_incident_event_incident FOREIGN KEY(incident_id) REFERENCES security_incidents(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_incident_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_incident_event CHECK(JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('007_key_rotation', 'Create secret and key rotation metadata without secret material.', <<<'SQL'
CREATE TABLE key_rotation_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, key_family_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 previous_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, new_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rotation_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 initiated_by_account_id BIGINT UNSIGNED NOT NULL, approved_by_account_id BIGINT UNSIGNED NOT NULL,
 not_before DATETIME(6) NOT NULL, overlap_until DATETIME(6) NULL, completed_at DATETIME(6) NULL, rolled_back_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_key_rotation_public(public_id), UNIQUE KEY uq_p12_key_rotation_new(key_family_code,new_key_id), KEY ix_p12_key_rotation_status(status_code,not_before,id),
 CONSTRAINT fk_p12_key_rotation_initiator FOREIGN KEY(initiated_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_key_rotation_approver FOREIGN KEY(approved_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_key_rotation CHECK(status_code IN ('PLANNED','OVERLAP','ACTIVE','COMPLETED','ROLLED_BACK','COMPROMISED') AND initiated_by_account_id<>approved_by_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('008_backup_artifacts', 'Create metadata for encrypted immutable backup artifacts.', <<<'SQL'
CREATE TABLE backup_artifacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, backup_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 backup_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, storage_reference_hash BINARY(32) NOT NULL,
 artifact_checksum BINARY(32) NOT NULL, encryption_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 schema_ledger_hash BINARY(32) NOT NULL, source_started_at DATETIME(6) NOT NULL, source_completed_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_backup_artifact_public(public_id), UNIQUE KEY uq_p12_backup_artifact_code(backup_code), KEY ix_p12_backup_freshness(status_code,source_completed_at,id),
 CONSTRAINT ck_p12_backup_artifact CHECK(backup_type IN ('FULL','INCREMENTAL','PITR_BASE','OBJECT_MANIFEST','CONFIG_MANIFEST') AND status_code IN ('REGISTERED','VERIFIED','INVALID','EXPIRED','REVOKED') AND source_completed_at>=source_started_at AND expires_at>source_completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('009_backup_verification', 'Create immutable backup verification evidence.', <<<'SQL'
CREATE TABLE backup_verification_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, backup_artifact_id BIGINT UNSIGNED NOT NULL,
 verifier_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, checksum_valid TINYINT(1) NOT NULL,
 encryption_metadata_valid TINYINT(1) NOT NULL, ledger_valid TINYINT(1) NOT NULL, outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_hash BINARY(32) NOT NULL, started_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_backup_verification_public(public_id), KEY ix_p12_backup_verification_artifact(backup_artifact_id,completed_at,id),
 CONSTRAINT fk_p12_backup_verification_artifact FOREIGN KEY(backup_artifact_id) REFERENCES backup_artifacts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_backup_verification CHECK(checksum_valid IN (0,1) AND encryption_metadata_valid IN (0,1) AND ledger_valid IN (0,1) AND outcome_code IN ('PASS','FAIL','INCOMPLETE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('010_restore_verification', 'Create isolated restore and reconciliation evidence.', <<<'SQL'
CREATE TABLE restore_verification_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, backup_artifact_id BIGINT UNSIGNED NOT NULL,
 environment_reference_hash BINARY(32) NOT NULL, isolation_verified TINYINT(1) NOT NULL, schema_verified TINYINT(1) NOT NULL,
 audit_verified TINYINT(1) NOT NULL, domain_verifiers_json JSON NOT NULL, achieved_rto_seconds INT UNSIGNED NULL,
 achieved_rpo_seconds INT UNSIGNED NULL, outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_hash BINARY(32) NOT NULL, started_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NOT NULL, destroyed_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_restore_verification_public(public_id), KEY ix_p12_restore_verification_artifact(backup_artifact_id,completed_at,id),
 CONSTRAINT fk_p12_restore_verification_artifact FOREIGN KEY(backup_artifact_id) REFERENCES backup_artifacts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_restore_verification CHECK(isolation_verified IN (0,1) AND schema_verified IN (0,1) AND audit_verified IN (0,1) AND outcome_code IN ('PASS','FAIL','INCOMPLETE') AND JSON_VALID(domain_verifiers_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('011_operation_receipts', 'Create P12 operation receipts for safe retry.', <<<'SQL'
CREATE TABLE p12_operation_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, actor_kind VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, operation_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 submission_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash BINARY(32) NOT NULL,
 outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, resource_public_id BINARY(16) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_operation_receipt_public(public_id), UNIQUE KEY uq_p12_operation_receipt(actor_kind,actor_reference,operation_code,submission_id),
 CONSTRAINT ck_p12_operation_receipt CHECK(actor_kind IN ('ACCOUNT','SERVICE','API_CLIENT') AND outcome_code IN ('SUCCEEDED','DENIED','FAILED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('012_incident_update_trigger', 'Protect incident events from updates.', "CREATE TRIGGER trg_p12_incident_event_no_update BEFORE UPDATE ON incident_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Incident events are immutable'"),
            $this->step('013_incident_delete_trigger', 'Protect incident events from deletion.', "CREATE TRIGGER trg_p12_incident_event_no_delete BEFORE DELETE ON incident_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Incident events are immutable'"),
            $this->step('014_backup_verification_update_trigger', 'Protect backup verification evidence from updates.', "CREATE TRIGGER trg_p12_backup_run_no_update BEFORE UPDATE ON backup_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Backup verification runs are immutable'"),
            $this->step('015_backup_verification_delete_trigger', 'Protect backup verification evidence from deletion.', "CREATE TRIGGER trg_p12_backup_run_no_delete BEFORE DELETE ON backup_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Backup verification runs are immutable'"),
            $this->step('016_restore_verification_update_trigger', 'Protect restore verification evidence from updates.', "CREATE TRIGGER trg_p12_restore_run_no_update BEFORE UPDATE ON restore_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Restore verification runs are immutable'"),
            $this->step('017_restore_verification_delete_trigger', 'Protect restore verification evidence from deletion.', "CREATE TRIGGER trg_p12_restore_run_no_delete BEFORE DELETE ON restore_verification_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Restore verification runs are immutable'"),
        ];
    }

    private function step(string $id, string $description, string $sql): SqlMigrationStep
    {
        return new SqlMigrationStep(new MigrationStepId($id), $description, $sql);
    }
}
