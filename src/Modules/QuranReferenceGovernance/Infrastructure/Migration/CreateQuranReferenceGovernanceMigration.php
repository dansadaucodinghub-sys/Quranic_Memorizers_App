<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\ExtendIdentityResolutionReviewIdempotencyMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateQuranReferenceGovernanceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260910060100_create_quran_reference_governance');
    }
    public function description(): string
    {
        return 'Create global Qur’an source, release, manifest, and append-only governance records.';
    }
    public function dependencies(): array
    {
        return [(new ExtendIdentityResolutionReviewIdempotencyMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_sources'), 'Create approved global Qur’an source registry.', <<<'SQL'
CREATE TABLE quran_reference_sources (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, source_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, authority_name VARCHAR(160) NOT NULL, source_title VARCHAR(255) NOT NULL, source_version VARCHAR(64) NOT NULL, content_role VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_format VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_reference VARCHAR(512) NOT NULL, usage_terms_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attribution_required TINYINT(1) NOT NULL, verbatim_only TINYINT(1) NOT NULL, runtime_download_allowed TINYINT(1) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_sources_public_id (public_id), UNIQUE KEY uq_quran_sources_code (source_code), KEY ix_quran_sources_status_role (status, content_role, id),
 CONSTRAINT ck_quran_sources_role CHECK (content_role IN ('CANONICAL_TEXT','STRUCTURAL_METADATA','SEARCH_TEXT')), CONSTRAINT ck_quran_sources_status CHECK (status IN ('APPROVED','RETIRED')), CONSTRAINT ck_quran_sources_flags CHECK (attribution_required IN (0,1) AND verbatim_only IN (0,1) AND runtime_download_allowed IN (0,1)), CONSTRAINT ck_quran_sources_version CHECK (version >= 1), CONSTRAINT ck_quran_sources_retired CHECK (status <> 'RETIRED' OR retired_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_artifacts'), 'Create checksummed repository-relative source artifacts.', <<<'SQL'
CREATE TABLE quran_source_artifacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, source_id BIGINT UNSIGNED NOT NULL, artifact_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, artifact_role VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, repository_relative_path VARCHAR(512) NOT NULL, original_filename VARCHAR(255) NOT NULL, media_type VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, byte_size BIGINT UNSIGNED NOT NULL, sha256 BINARY(32) NOT NULL, acquisition_profile VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, acquired_at DATETIME(6) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, rejected_at DATETIME(6) NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_artifacts_public_id (public_id), UNIQUE KEY uq_quran_artifacts_code (artifact_code), UNIQUE KEY uq_quran_artifacts_source_sha256 (source_id, sha256), KEY ix_quran_artifacts_source_status (source_id, status, id), CONSTRAINT fk_quran_artifacts_source FOREIGN KEY (source_id) REFERENCES quran_reference_sources (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_artifacts_role CHECK (artifact_role IN ('CANONICAL_TEXT','STRUCTURAL_METADATA','SEARCH_TEXT')), CONSTRAINT ck_quran_artifacts_size CHECK (byte_size > 0), CONSTRAINT ck_quran_artifacts_status CHECK (status IN ('REGISTERED','VERIFIED','REJECTED')), CONSTRAINT ck_quran_artifacts_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_releases'), 'Create immutable-governed Qur’an reference releases.', <<<'SQL'
CREATE TABLE quran_reference_releases (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, release_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, manifest_sha256 BINARY(32) NULL, manifest_schema_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, validation_policy_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_by_account_id BIGINT UNSIGNED NOT NULL, approved_by_account_id BIGINT UNSIGNED NULL, activated_by_account_id BIGINT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, staged_at DATETIME(6) NULL, validated_at DATETIME(6) NULL, approved_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, superseded_at DATETIME(6) NULL, rejected_at DATETIME(6) NULL, updated_at DATETIME(6) NOT NULL, active_release_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_releases_public_id (public_id), UNIQUE KEY uq_quran_releases_code (release_code), UNIQUE KEY uq_quran_releases_version (release_version), UNIQUE KEY uq_quran_releases_active (active_release_marker), KEY ix_quran_releases_status_created (status, created_at, id), CONSTRAINT fk_quran_release_creator FOREIGN KEY (created_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_release_approver FOREIGN KEY (approved_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_release_activator FOREIGN KEY (activated_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_releases_status CHECK (status IN ('DRAFT','STAGED','VALIDATED','APPROVED','ACTIVE','SUPERSEDED','REJECTED')), CONSTRAINT ck_quran_releases_version CHECK (version >= 1), CONSTRAINT ck_quran_releases_active CHECK (status <> 'ACTIVE' OR (approved_at IS NOT NULL AND activated_at IS NOT NULL)), CONSTRAINT ck_quran_releases_superseded CHECK (status <> 'SUPERSEDED' OR superseded_at IS NOT NULL), CONSTRAINT ck_quran_releases_rejected CHECK (status <> 'REJECTED' OR rejected_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_create_release_artifacts'), 'Assign immutable source artifacts to releases.', <<<'SQL'
CREATE TABLE quran_release_artifacts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, source_artifact_id BIGINT UNSIGNED NOT NULL, artifact_role VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_release_artifacts_public_id (public_id), UNIQUE KEY uq_quran_release_artifacts_role (release_id, artifact_role), UNIQUE KEY uq_quran_release_artifacts_source (release_id, source_artifact_id), CONSTRAINT fk_quran_release_artifacts_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_release_artifacts_artifact FOREIGN KEY (source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_release_artifacts_role CHECK (artifact_role IN ('CANONICAL_TEXT','STRUCTURAL_METADATA','SEARCH_TEXT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_create_validations'), 'Create append-only release validation evidence.', <<<'SQL'
CREATE TABLE quran_release_validations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, validator_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, validator_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, result VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_summary_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, evidence_sha256 BINARY(32) NOT NULL, executed_by_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, executed_by_account_id BIGINT UNSIGNED NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_validations_public_id (public_id), KEY ix_quran_validations_release_time (release_id, occurred_at, id), CONSTRAINT fk_quran_validations_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_validations_account FOREIGN KEY (executed_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_validations_result CHECK (result IN ('PASS','FAIL')), CONSTRAINT ck_quran_validations_actor CHECK (executed_by_type IN ('ACCOUNT','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_create_events'), 'Create append-only release lifecycle history.', <<<'SQL'
CREATE TABLE quran_release_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, from_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_events_public_id (public_id), KEY ix_quran_events_release_time (release_id, occurred_at, id), CONSTRAINT fk_quran_events_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_events_account FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_events_type CHECK (event_type IN ('CREATED','STAGED','VALIDATED','APPROVED','ACTIVATED','SUPERSEDED','REJECTED')), CONSTRAINT ck_quran_events_actor CHECK (actor_type IN ('ACCOUNT','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('007_validations_no_update'), 'Protect validation evidence from mutation.', "CREATE TRIGGER trg_quran_release_validations_no_update BEFORE UPDATE ON quran_release_validations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release validations are append-only'"),
            new SqlMigrationStep(new MigrationStepId('008_validations_no_delete'), 'Protect validation evidence from deletion.', "CREATE TRIGGER trg_quran_release_validations_no_delete BEFORE DELETE ON quran_release_validations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release validations are append-only'"),
            new SqlMigrationStep(new MigrationStepId('009_events_no_update'), 'Protect lifecycle events from mutation.', "CREATE TRIGGER trg_quran_release_events_no_update BEFORE UPDATE ON quran_release_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('010_events_no_delete'), 'Protect lifecycle events from deletion.', "CREATE TRIGGER trg_quran_release_events_no_delete BEFORE DELETE ON quran_release_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release events are append-only'"),
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
