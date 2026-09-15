<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Immutable issued certificate evidence and resumable issuance jobs. */
final readonly class CreateCertificateIssuanceMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260915101000_create_certificate_issuance'); }
    public function description(): string { return 'Create P8 certificates, immutable artifacts, events, and issuance jobs.'; }
    public function dependencies(): array { return [(new CreateCertificateGovernanceMigration())->id()]; }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_certificates'), 'Create prepared and issued certificate aggregates.', <<<'SQL'
CREATE TABLE certificates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 certificate_number VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, verification_code_hash BINARY(32) NOT NULL, verification_code_fingerprint BINARY(16) NOT NULL,
 certificate_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, person_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NULL,
 result_publication_id BIGINT UNSIGNED NOT NULL, result_run_id BIGINT UNSIGNED NOT NULL, result_row_id BIGINT UNSIGNED NOT NULL,
 template_id BIGINT UNSIGNED NOT NULL, signing_key_id BIGINT UNSIGNED NOT NULL, supersedes_certificate_id BIGINT UNSIGNED NULL, replacement_certificate_id BIGINT UNSIGNED NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PREPARED', current_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('PREPARED','ISSUED') THEN 1 ELSE NULL END) STORED,
 display_name VARCHAR(320) NOT NULL, display_name_source_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 manifest_schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1, manifest_canonical_json JSON NULL, manifest_sha256 BINARY(32) NULL, detached_signature BINARY(64) NULL, signature_algorithm VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
 pdf_sha256 BINARY(32) NULL, result_package_sha256 BINARY(32) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 prepared_by_account_id BIGINT UNSIGNED NOT NULL, issued_by_account_id BIGINT UNSIGNED NULL, revoked_by_account_id BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, prepared_at DATETIME(6) NOT NULL, issued_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, superseded_at DATETIME(6) NULL, voided_at DATETIME(6) NULL, archived_at DATETIME(6) NULL,
 revocation_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, void_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p8_certificate_public(public_id), UNIQUE KEY uq_p8_certificate_number(workspace_id,certificate_number), UNIQUE KEY uq_p8_certificate_verify(verification_code_hash),
 UNIQUE KEY uq_p8_certificate_current(workspace_id,result_publication_id,result_row_id,certificate_type,template_id,current_marker), UNIQUE KEY uq_p8_certificate_workspace_id(workspace_id,id),
 KEY ix_p8_certificate_person(person_id,status,issued_at,id), KEY ix_p8_certificate_publication(workspace_id,result_publication_id,status,id),
 CONSTRAINT fk_p8_certificate_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_person FOREIGN KEY(person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_account FOREIGN KEY(account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_publication FOREIGN KEY(workspace_id,result_publication_id) REFERENCES competition_result_publications(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_result_run FOREIGN KEY(workspace_id,result_run_id) REFERENCES competition_result_runs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_result_row FOREIGN KEY(result_row_id) REFERENCES competition_result_rows(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_template FOREIGN KEY(workspace_id,template_id) REFERENCES certificate_templates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_key FOREIGN KEY(signing_key_id) REFERENCES certificate_signing_keys(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_supersedes FOREIGN KEY(workspace_id,supersedes_certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_replacement FOREIGN KEY(workspace_id,replacement_certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_preparer FOREIGN KEY(prepared_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_issuer FOREIGN KEY(issued_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_certificate_revoker FOREIGN KEY(revoked_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p8_certificate CHECK(manifest_schema_version>=1 AND version>=1 AND certificate_type IN ('WINNER','PLACEMENT','PARTICIPATION','RECOGNITION','OTHER_APPROVED') AND status IN ('PREPARED','ISSUED','REVOKED','SUPERSEDED','VOIDED','ARCHIVED') AND (status<>'ISSUED' OR (issued_at IS NOT NULL AND issued_by_account_id IS NOT NULL AND manifest_canonical_json IS NOT NULL AND manifest_sha256 IS NOT NULL AND detached_signature IS NOT NULL AND signature_algorithm='ED25519' AND pdf_sha256 IS NOT NULL)) AND (status<>'REVOKED' OR (revoked_at IS NOT NULL AND revoked_by_account_id IS NOT NULL AND revocation_reason_code IS NOT NULL)) AND (status<>'SUPERSEDED' OR (superseded_at IS NOT NULL AND replacement_certificate_id IS NOT NULL)) AND (status<>'VOIDED' OR (voided_at IS NOT NULL AND void_reason_code IS NOT NULL)) AND (status<>'ARCHIVED' OR archived_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_artifacts'), 'Create immutable PDF and manifest artifact metadata.', <<<'SQL'
CREATE TABLE certificate_artifacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, certificate_id BIGINT UNSIGNED NOT NULL,
 artifact_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, storage_provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 storage_object_key VARCHAR(320) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, media_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, byte_size BIGINT UNSIGNED NOT NULL,
 sha256 BINARY(32) NOT NULL, renderer_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, renderer_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p8_artifact_public(public_id), UNIQUE KEY uq_p8_artifact_type(workspace_id,certificate_id,artifact_type), UNIQUE KEY uq_p8_artifact_workspace_id(workspace_id,id),
 CONSTRAINT fk_p8_artifact_certificate FOREIGN KEY(workspace_id,certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p8_artifact CHECK(artifact_type IN ('PDF','MANIFEST') AND byte_size>=1 AND ((artifact_type='PDF' AND media_type='application/pdf') OR (artifact_type='MANIFEST' AND media_type='application/json')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_events'), 'Create append-only certificate lifecycle events.', "CREATE TABLE certificate_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, certificate_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, from_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_type VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, correlation_id BINARY(16) NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p8_certificate_event_public(public_id), KEY ix_p8_certificate_event(workspace_id,certificate_id,id), CONSTRAINT fk_p8_certificate_event_certificate FOREIGN KEY(workspace_id,certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p8_certificate_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p8_certificate_event CHECK(event_type IN ('PREPARED','ISSUANCE_STARTED','ISSUED','REVOKED','SUPERSEDED','VOIDED','ARCHIVED','VERIFICATION_WARNING_RECORDED') AND actor_type IN ('ACCOUNT','SYSTEM') AND ((actor_type='ACCOUNT' AND actor_account_id IS NOT NULL) OR (actor_type='SYSTEM' AND actor_account_id IS NULL))) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('004_jobs'), "Create idempotent certificate issuance job records.", "CREATE TABLE certificate_issuance_jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, certificate_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING', attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, idempotency_fingerprint BINARY(32) NOT NULL, lease_owner VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, lease_expires_at DATETIME(6) NULL, last_error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL, PRIMARY KEY(id), UNIQUE KEY uq_p8_issuance_job_public(public_id), UNIQUE KEY uq_p8_issuance_job_certificate(workspace_id,certificate_id), UNIQUE KEY uq_p8_issuance_job_fingerprint(workspace_id,idempotency_fingerprint), KEY ix_p8_issuance_job_poll(workspace_id,status,lease_expires_at,id), CONSTRAINT fk_p8_issuance_job_certificate FOREIGN KEY(workspace_id,certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p8_issuance_job CHECK(status IN ('PENDING','PROCESSING','COMPLETE','FAILED') AND attempts<=100)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('005_event_immutable'), 'Prevent certificate event mutation.', "CREATE TRIGGER trg_p8_certificate_events_no_update BEFORE UPDATE ON certificate_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certificate events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('006_event_no_delete'), 'Prevent certificate event deletion.', "CREATE TRIGGER trg_p8_certificate_events_no_delete BEFORE DELETE ON certificate_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certificate events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('007_artifact_immutable'), 'Prevent certificate artifact mutation.', "CREATE TRIGGER trg_p8_certificate_artifacts_no_update BEFORE UPDATE ON certificate_artifacts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certificate artifacts are immutable'"),
            new SqlMigrationStep(new MigrationStepId('008_artifact_no_delete'), 'Prevent certificate artifact deletion.', "CREATE TRIGGER trg_p8_certificate_artifacts_no_delete BEFORE DELETE ON certificate_artifacts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Certificate artifacts are immutable'"),
        ];
    }
    public function down(): array { return []; }
    public function reversible(): bool { return false; }
}
