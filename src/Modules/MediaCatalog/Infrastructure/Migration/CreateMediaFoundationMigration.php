<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Infrastructure\Migration;

use Qmdb\Modules\TrustedArchive\Infrastructure\Migration\CreateLegacyRecordImportMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** P9 private originals, immutable upload parts, safe variants, and leased processing work. */
final readonly class CreateMediaFoundationMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260915110000_create_media_foundation'); }
    public function description(): string { return 'Create P9 private media assets, resumable uploads, variants, and processing jobs.'; }
    public function dependencies(): array { return [(new CreateLegacyRecordImportMigration())->id()]; }
    public function reversible(): bool { return false; }
    public function down(): array { return []; }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_assets'), 'Create private tenant media assets.', <<<'SQL'
CREATE TABLE media_assets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, owner_person_id BIGINT UNSIGNED NULL, created_by_account_id BIGINT UNSIGNED NOT NULL,
 purpose_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, media_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'STAGING', original_storage_provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, original_storage_object_key VARCHAR(320) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, original_filename_safe VARCHAR(180) NOT NULL, detected_mime_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL, byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0, original_sha256 BINARY(32) NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, withdrawn_at DATETIME(6) NULL, archived_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_asset_public(public_id), UNIQUE KEY uq_p9_asset_workspace_id(workspace_id,id), KEY ix_p9_asset_queue(workspace_id,status,created_at,id),
 CONSTRAINT fk_p9_asset_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p9_asset_person FOREIGN KEY(owner_person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p9_asset_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p9_asset CHECK(media_kind IN ('AUDIO','VIDEO','IMAGE','DOCUMENT') AND status IN ('STAGING','QUARANTINED','SCANNING','PROCESSING','PENDING_MODERATION','APPROVED','PUBLISHED','WITHDRAWN','ARCHIVED','REJECTED') AND byte_size>=0 AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_upload_sessions'), 'Create bounded resumable upload sessions.', <<<'SQL'
CREATE TABLE media_upload_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL, created_by_account_id BIGINT UNSIGNED NOT NULL, expected_byte_size BIGINT UNSIGNED NOT NULL, chunk_size INT UNSIGNED NOT NULL, expected_sha256 BINARY(32) NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'OPEN', expires_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_upload_public(public_id), UNIQUE KEY uq_p9_upload_asset(asset_id), UNIQUE KEY uq_p9_upload_workspace_id(workspace_id,id), KEY ix_p9_upload_expiry(status,expires_at,id), CONSTRAINT fk_p9_upload_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p9_upload_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p9_upload CHECK(expected_byte_size BETWEEN 1 AND 10737418240 AND chunk_size BETWEEN 262144 AND 16777216 AND status IN ('OPEN','COMPLETED','EXPIRED','CANCELLED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_upload_parts'), 'Create immutable idempotent upload parts.', <<<'SQL'
CREATE TABLE media_upload_parts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, upload_session_id BIGINT UNSIGNED NOT NULL, part_number INT UNSIGNED NOT NULL, byte_start BIGINT UNSIGNED NOT NULL, byte_end BIGINT UNSIGNED NOT NULL, byte_size INT UNSIGNED NOT NULL, sha256 BINARY(32) NOT NULL, staging_object_key VARCHAR(320) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_upload_part(upload_session_id,part_number), UNIQUE KEY uq_p9_upload_part_range(upload_session_id,byte_start,byte_end), CONSTRAINT fk_p9_upload_part_session FOREIGN KEY(workspace_id,upload_session_id) REFERENCES media_upload_sessions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p9_upload_part CHECK(byte_end>=byte_start AND byte_size=byte_end-byte_start+1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_variants'), 'Create immutable processed variants.', <<<'SQL'
CREATE TABLE media_variants (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL, variant_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, storage_provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, storage_object_key VARCHAR(320) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, mime_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, byte_size BIGINT UNSIGNED NOT NULL, sha256 BINARY(32) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'READY', is_public_safe TINYINT NOT NULL DEFAULT 0, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_variant_public(public_id), UNIQUE KEY uq_p9_variant_code(asset_id,variant_code), KEY ix_p9_variant_delivery(asset_id,status,is_public_safe,id), CONSTRAINT fk_p9_variant_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p9_variant CHECK(status IN ('READY','WITHDRAWN','ARCHIVED') AND is_public_safe IN (0,1) AND byte_size>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_jobs'), 'Create lease-based retryable media jobs.', <<<'SQL'
CREATE TABLE media_processing_jobs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL, job_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'QUEUED', attempt INT UNSIGNED NOT NULL DEFAULT 0, max_attempts INT UNSIGNED NOT NULL DEFAULT 3, lease_expires_at DATETIME(6) NULL, available_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL, safe_failure_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_job_public(public_id), UNIQUE KEY uq_p9_job_once(asset_id,job_type), KEY ix_p9_job_claim(status,available_at,lease_expires_at,id), CONSTRAINT fk_p9_job_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p9_job CHECK(job_type IN ('SCAN','PROBE','PROCESS_AUDIO','PROCESS_VIDEO','MODERATE','RECONCILE') AND status IN ('QUEUED','LEASED','SUCCEEDED','FAILED','DEAD') AND attempt<=max_attempts AND max_attempts BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }
}
