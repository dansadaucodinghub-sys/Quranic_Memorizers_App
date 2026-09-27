<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-020: controlled report and export projections; public datasets are excluded. */
final readonly class CreateReportingExportRuntimeMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260925103000_create_reporting_export_runtime');
    }

    public function description(): string
    {
        return 'Create governed report definitions, runs, exports, artifacts, receipts, and notifications.';
    }

    public function dependencies(): array
    {
        return [(new CreateAnalyticsSnapshotRuntimeMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_definitions'), 'Create versioned report definitions.', <<<'SQL'
CREATE TABLE report_definitions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, scope_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 query_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, allowed_formats_json JSON NOT NULL,
 privacy_policy_id BIGINT UNSIGNED NOT NULL, row_limit INT UNSIGNED NOT NULL, byte_limit INT UNSIGNED NOT NULL,
 sensitive_flag TINYINT(1) NOT NULL, approval_required TINYINT(1) NOT NULL, status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status_code='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_report_definition_public(public_id), UNIQUE KEY uq_p11_report_definition_version(code,version),
 UNIQUE KEY uq_p11_report_definition_active(code,active_marker),
 CONSTRAINT fk_p11_report_privacy FOREIGN KEY(privacy_policy_id) REFERENCES analytics_privacy_policies(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_report_definition CHECK(scope_code IN ('WORKSPACE','PLATFORM') AND query_code IN ('WORKSPACE_SEARCH_INVENTORY','WORKSPACE_RESULTS_SUMMARY','NATIONAL_AGGREGATE_SUMMARY') AND status_code IN ('DRAFT','ACTIVE','RETIRED') AND version>=1 AND row_limit BETWEEN 1 AND 100000 AND byte_limit BETWEEN 1024 AND 104857600 AND JSON_VALID(allowed_formats_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_runs'), 'Create bounded report run workflow.', <<<'SQL'
CREATE TABLE report_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 report_definition_id BIGINT UNSIGNED NOT NULL, requester_account_id BIGINT UNSIGNED NOT NULL,
 approver_account_id BIGINT UNSIGNED NULL, purpose VARCHAR(500) NOT NULL, parameters_json JSON NOT NULL,
 status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_watermark VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, next_attempt_at DATETIME(6) NOT NULL, lease_owner BINARY(16) NULL,
 lease_expires_at DATETIME(6) NULL, approved_at DATETIME(6) NULL, completed_at DATETIME(6) NULL,
 failed_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, expires_at DATETIME(6) NOT NULL,
 error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, checksum BINARY(32) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_report_run_public(public_id), KEY ix_p11_report_run_due(status_code,next_attempt_at,id),
 KEY ix_p11_report_run_requester(requester_account_id,created_at,id), KEY ix_p11_report_run_workspace(workspace_id,status_code,created_at,id),
 CONSTRAINT fk_p11_report_run_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_report_run_definition FOREIGN KEY(report_definition_id) REFERENCES report_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_report_run_requester FOREIGN KEY(requester_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_report_run_approver FOREIGN KEY(approver_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_report_run CHECK(status_code IN ('PENDING','APPROVAL_REQUIRED','APPROVED','LEASED','RETRY','COMPLETED','FAILED','EXPIRED','REVOKED') AND attempt_count<=10 AND expires_at>created_at AND requester_account_id<>COALESCE(approver_account_id,0) AND JSON_VALID(parameters_json) AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_exports'), 'Create controlled export jobs.', <<<'SQL'
CREATE TABLE export_jobs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, report_run_id BIGINT UNSIGNED NOT NULL,
 requester_account_id BIGINT UNSIGNED NOT NULL, format_code VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME(6) NOT NULL, lease_owner BINARY(16) NULL, lease_expires_at DATETIME(6) NULL,
 completed_at DATETIME(6) NULL, failed_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, expires_at DATETIME(6) NOT NULL,
 error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_export_public(public_id), UNIQUE KEY uq_p11_export_identity(report_run_id,format_code),
 KEY ix_p11_export_due(status_code,next_attempt_at,id),
 CONSTRAINT fk_p11_export_report_run FOREIGN KEY(report_run_id) REFERENCES report_runs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_export_requester FOREIGN KEY(requester_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_export CHECK(format_code IN ('CSV','JSON') AND status_code IN ('PENDING','LEASED','RETRY','COMPLETED','FAILED','EXPIRED','REVOKED') AND attempt_count<=10 AND expires_at>created_at AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_artifacts'), 'Create private expiring export artifact metadata.', <<<'SQL'
CREATE TABLE export_artifacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, export_job_id BIGINT UNSIGNED NOT NULL,
 private_storage_key VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, media_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 row_count INT UNSIGNED NOT NULL, byte_count INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL,
 created_at DATETIME(6) NOT NULL, expires_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL, deleted_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_export_artifact_public(public_id), UNIQUE KEY uq_p11_export_artifact_job(export_job_id),
 UNIQUE KEY uq_p11_export_artifact_key(private_storage_key), KEY ix_p11_export_artifact_expiry(expires_at,deleted_at,id),
 CONSTRAINT fk_p11_export_artifact_job FOREIGN KEY(export_job_id) REFERENCES export_jobs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_export_artifact CHECK(media_type IN ('text/csv','application/json') AND expires_at>created_at AND byte_count<=104857600 AND row_count<=100000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_events'), 'Create append-only report and export events.', <<<'SQL'
CREATE TABLE reporting_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, subject_kind VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_account_id BIGINT UNSIGNED NULL, safe_metadata_json JSON NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_reporting_event_public(public_id), KEY ix_p11_reporting_event_subject(subject_kind,subject_id,id),
 CONSTRAINT fk_p11_reporting_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_reporting_event CHECK(subject_kind IN ('REPORT_RUN','EXPORT_JOB','EXPORT_ARTIFACT') AND event_code IN ('CREATED','APPROVAL_REQUIRED','APPROVED','CLAIMED','COMPLETED','RETRY_SCHEDULED','FAILED','DOWNLOADED','EXPIRED','REVOKED','DELETED') AND JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_receipts'), 'Create idempotent P11 operation receipts.', <<<'SQL'
CREATE TABLE p11_operation_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 submission_id BINARY(16) NOT NULL, request_hash BINARY(32) NOT NULL, subject_public_id BINARY(16) NULL,
 response_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_receipt_public(public_id), UNIQUE KEY uq_p11_receipt_submission(actor_account_id,operation_code,submission_id),
 CONSTRAINT fk_p11_receipt_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p11_receipt_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_receipt CHECK(operation_code IN ('REPORT_REQUEST','REPORT_APPROVE','REPORT_REVOKE','EXPORT_REQUEST','EXPORT_REVOKE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('007_notifications'), 'Create minimal P11 job notification intents.', <<<'SQL'
CREATE TABLE p11_notification_intents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, recipient_account_id BIGINT UNSIGNED NOT NULL,
 type_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, subject_public_id BINARY(16) NOT NULL,
 subject_version INT UNSIGNED NOT NULL, safe_state_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME(6) NOT NULL, lease_owner BINARY(16) NULL, lease_expires_at DATETIME(6) NULL,
 delivered_at DATETIME(6) NULL, last_error_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_notice_public(public_id), UNIQUE KEY uq_p11_notice_dedupe(recipient_account_id,type_code,subject_public_id,subject_version),
 KEY ix_p11_notice_due(status_code,next_attempt_at,id),
 CONSTRAINT fk_p11_notice_recipient FOREIGN KEY(recipient_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_notice CHECK(type_code IN ('REPORT_COMPLETED','REPORT_FAILED','REPORT_APPROVAL_REQUIRED','EXPORT_READY','EXPORT_FAILED','EXPORT_EXPIRING') AND status_code IN ('PENDING','LEASED','RETRY','DELIVERED','DEAD_LETTER') AND subject_version>=1 AND attempt_count<=10 AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('008_reporting_event_update'), 'Reject reporting event changes.', <<<'SQL'
CREATE TRIGGER trg_p11_reporting_event_no_update BEFORE UPDATE ON reporting_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reporting events are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('009_reporting_event_delete'), 'Reject reporting event deletion.', <<<'SQL'
CREATE TRIGGER trg_p11_reporting_event_no_delete BEFORE DELETE ON reporting_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reporting events are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('010_artifact_update'), 'Allow only revocation and deletion metadata on artifacts.', <<<'SQL'
CREATE TRIGGER trg_p11_export_artifact_guard BEFORE UPDATE ON export_artifacts
FOR EACH ROW SET NEW.media_type=IF(OLD.public_id<>NEW.public_id OR OLD.export_job_id<>NEW.export_job_id OR OLD.private_storage_key<>NEW.private_storage_key OR OLD.media_type<>NEW.media_type OR OLD.row_count<>NEW.row_count OR OLD.byte_count<>NEW.byte_count OR OLD.checksum<>NEW.checksum OR OLD.created_at<>NEW.created_at OR OLD.expires_at<>NEW.expires_at OR (OLD.revoked_at IS NOT NULL AND NOT (NEW.revoked_at<=>OLD.revoked_at)) OR (OLD.deleted_at IS NOT NULL AND NOT (NEW.deleted_at<=>OLD.deleted_at)),'immutable',NEW.media_type)
SQL),
        ];
    }
}
