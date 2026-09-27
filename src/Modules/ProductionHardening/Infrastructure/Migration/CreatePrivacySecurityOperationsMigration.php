<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-018: privacy lifecycle, scoped support access, retention, and holds. */
final readonly class CreatePrivacySecurityOperationsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260927101000_create_privacy_security_operations');
    }

    public function description(): string
    {
        return 'Create verified privacy cases, retention policies, data holds, and controlled support access.';
    }

    public function dependencies(): array
    {
        return [(new CreateNotificationsIntegrationsMigration())->id()];
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
            $this->step('001_processing_purposes', 'Create governed processing-purpose versions.', <<<'SQL'
CREATE TABLE processing_purposes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(191) NOT NULL, effective_from DATETIME(6) NOT NULL, effective_until DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_processing_purpose_code(code), KEY ix_p12_processing_purpose_name(name), KEY ix_p12_processing_purpose_created(created_at),
 CONSTRAINT ck_p12_processing_purpose_effective CHECK(effective_until IS NULL OR effective_until>effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('002_privacy_notices', 'Create immutable official privacy-notice versions.', <<<'SQL'
CREATE TABLE privacy_notice_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, processing_purpose_id BIGINT UNSIGNED NOT NULL,
 version_number INT UNSIGNED NOT NULL, content_checksum BINARY(32) NOT NULL,
 status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, effective_from DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_privacy_notice_version(processing_purpose_id,version_number),
 KEY ix_p12_privacy_notice_purpose(processing_purpose_id,status_code,effective_from), KEY ix_p12_privacy_notice_checksum(content_checksum),
 CONSTRAINT fk_p12_privacy_notice_purpose FOREIGN KEY(processing_purpose_id) REFERENCES processing_purposes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_privacy_notice_version CHECK(version_number>=1),
 CONSTRAINT ck_p12_privacy_notice_status CHECK(status_code IN ('DRAFT','ACTIVE','SUPERSEDED','WITHDRAWN'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('003_privacy_requests', 'Create verified personal-data request cases.', <<<'SQL'
CREATE TABLE privacy_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 requester_account_id BIGINT UNSIGNED NOT NULL, subject_person_public_id BINARY(16) NOT NULL, guardian_person_public_id BINARY(16) NULL,
 request_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, authority_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 scope_json JSON NOT NULL, status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 identity_verified_at DATETIME(6) NULL, due_at DATETIME(6) NOT NULL, decision_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 decision_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, closed_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_privacy_request_public(public_id), KEY ix_p12_privacy_request_workspace(workspace_id,status_code,due_at,id),
 KEY ix_p12_privacy_request_requester(requester_account_id,created_at,id),
 CONSTRAINT fk_p12_privacy_request_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_privacy_request_requester FOREIGN KEY(requester_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_privacy_request CHECK(request_type IN ('ACCESS','CORRECTION','EXPORT','ERASURE','RESTRICTION','OBJECTION','CHILD_DATA') AND authority_code IN ('SELF','GUARDIAN','AUTHORIZED_REPRESENTATIVE') AND status_code IN ('RECEIVED','IDENTITY_PENDING','VERIFIED','ASSIGNED','DISCOVERY','REVIEW','APPROVED','PARTIALLY_APPROVED','REJECTED','DELIVERED','CLOSED','ON_HOLD') AND JSON_VALID(scope_json) AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('004_privacy_assignments', 'Create scoped privacy-case assignments.', <<<'SQL'
CREATE TABLE privacy_request_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, privacy_request_id BIGINT UNSIGNED NOT NULL,
 assignee_account_id BIGINT UNSIGNED NOT NULL, assigned_by_account_id BIGINT UNSIGNED NOT NULL,
 role_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, assigned_at DATETIME(6) NOT NULL, ended_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_privacy_assignment_public(public_id), KEY ix_p12_privacy_assignment_active(assignee_account_id,ended_at,id),
 CONSTRAINT fk_p12_privacy_assignment_request FOREIGN KEY(privacy_request_id) REFERENCES privacy_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_privacy_assignment_assignee FOREIGN KEY(assignee_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_privacy_assignment_assigner FOREIGN KEY(assigned_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_privacy_assignment CHECK(role_code IN ('PRIVACY_OFFICER','IDENTITY_VERIFIER','RECORDS_CUSTODIAN','LEGAL_REVIEWER'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('005_privacy_events', 'Create immutable minimized privacy-case events.', <<<'SQL'
CREATE TABLE privacy_request_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, privacy_request_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NULL, event_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 safe_metadata_json JSON NOT NULL, correlation_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, occurred_at DATETIME(6) NOT NULL,
 previous_hash BINARY(32) NULL, event_hash BINARY(32) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_privacy_event_public(public_id), UNIQUE KEY uq_p12_privacy_event_hash(event_hash),
 KEY ix_p12_privacy_event_case(privacy_request_id,id),
 CONSTRAINT fk_p12_privacy_event_request FOREIGN KEY(privacy_request_id) REFERENCES privacy_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_privacy_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_privacy_event CHECK(JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('006_retention_policies', 'Create versioned retention policies.', <<<'SQL'
CREATE TABLE retention_policy_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, policy_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, purpose_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 retention_days INT UNSIGNED NOT NULL, disposition_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 legal_basis_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, policy_json JSON NOT NULL,
 version INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL, status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 effective_at DATETIME(6) NOT NULL, retired_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_retention_policy_public(public_id), UNIQUE KEY uq_p12_retention_policy_version(policy_code,version),
 KEY ix_p12_retention_policy_active(subject_kind,status_code,effective_at),
 CONSTRAINT ck_p12_retention_policy CHECK(retention_days BETWEEN 1 AND 36500 AND disposition_code IN ('REVIEW','ANONYMIZE','DELETE','ARCHIVE') AND version>=1 AND status_code IN ('ACTIVE','RETIRED') AND JSON_VALID(policy_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('007_data_holds', 'Create enforceable legal and incident data holds.', <<<'SQL'
CREATE TABLE data_holds (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 subject_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, subject_public_id BINARY(16) NULL,
 scope_json JSON NOT NULL, reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 placed_by_account_id BIGINT UNSIGNED NOT NULL, released_by_account_id BIGINT UNSIGNED NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, starts_at DATETIME(6) NOT NULL, expires_at DATETIME(6) NULL,
 released_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_data_hold_public(public_id), KEY ix_p12_data_hold_subject(workspace_id,subject_kind,subject_public_id,status_code),
 CONSTRAINT fk_p12_data_hold_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_data_hold_placer FOREIGN KEY(placed_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_data_hold_releaser FOREIGN KEY(released_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_data_hold CHECK(status_code IN ('ACTIVE','RELEASED','EXPIRED') AND JSON_VALID(scope_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('008_anonymization_events', 'Create immutable disposition and anonymization evidence.', <<<'SQL'
CREATE TABLE anonymization_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 privacy_request_id BIGINT UNSIGNED NULL, retention_policy_id BIGINT UNSIGNED NOT NULL,
 subject_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, subject_reference_hash BINARY(32) NOT NULL,
 disposition_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, affected_records INT UNSIGNED NOT NULL,
 manifest_hash BINARY(32) NOT NULL, operation_key BINARY(32) NOT NULL, performed_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_anonymization_public(public_id), UNIQUE KEY uq_p12_anonymization_operation(operation_key),
 KEY ix_p12_anonymization_subject(workspace_id,subject_kind,performed_at,id),
 CONSTRAINT fk_p12_anonymization_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_anonymization_request FOREIGN KEY(privacy_request_id) REFERENCES privacy_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_anonymization_policy FOREIGN KEY(retention_policy_id) REFERENCES retention_policy_records(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_anonymization CHECK(disposition_code IN ('ANONYMIZED','DELETED','ARCHIVED','NO_ACTION_HOLD'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('009_data_exports', 'Create expiring privacy-export delivery evidence.', <<<'SQL'
CREATE TABLE data_export_deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, privacy_request_id BIGINT UNSIGNED NOT NULL,
 artifact_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, artifact_checksum BINARY(32) NOT NULL,
 recipient_account_id BIGINT UNSIGNED NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 approved_by_account_id BIGINT UNSIGNED NOT NULL, expires_at DATETIME(6) NOT NULL, downloaded_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_data_export_public(public_id), KEY ix_p12_data_export_expiry(status_code,expires_at,id),
 CONSTRAINT fk_p12_data_export_request FOREIGN KEY(privacy_request_id) REFERENCES privacy_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_data_export_recipient FOREIGN KEY(recipient_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_data_export_approver FOREIGN KEY(approved_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_data_export CHECK(status_code IN ('READY','DOWNLOADED','EXPIRED','REVOKED') AND recipient_account_id<>approved_by_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('010_privacy_event_update_trigger', 'Protect privacy events from updates.', "CREATE TRIGGER trg_p12_privacy_event_no_update BEFORE UPDATE ON privacy_request_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Privacy request events are immutable'"),
            $this->step('011_privacy_event_delete_trigger', 'Protect privacy events from deletion.', "CREATE TRIGGER trg_p12_privacy_event_no_delete BEFORE DELETE ON privacy_request_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Privacy request events are immutable'"),
            $this->step('012_anonymization_update_trigger', 'Protect disposition evidence from updates.', "CREATE TRIGGER trg_p12_anonymization_no_update BEFORE UPDATE ON anonymization_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Anonymization events are immutable'"),
            $this->step('013_anonymization_delete_trigger', 'Protect disposition evidence from deletion.', "CREATE TRIGGER trg_p12_anonymization_no_delete BEFORE DELETE ON anonymization_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Anonymization events are immutable'"),
        ];
    }

    private function step(string $id, string $description, string $sql): SqlMigrationStep
    {
        return new SqlMigrationStep(new MigrationStepId($id), $description, $sql);
    }
}
