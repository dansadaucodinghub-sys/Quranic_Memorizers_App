<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only P9 runtime evidence. No media bytes or public storage paths are persisted. */
final readonly class ExtendMediaEvidenceRuntimeMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260915111000_extend_media_evidence_runtime'); }
    public function description(): string { return 'Extend P9 media evidence with scans, immutable events, holds, and delivery policy.'; }
    public function dependencies(): array { return [(new CreateMediaFoundationMigration())->id()]; }
    public function reversible(): bool { return false; }
    public function down(): array { return []; }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_job_workspace_candidate'), 'Add the P9 job composite workspace candidate key.', <<<'SQL'
ALTER TABLE media_processing_jobs ADD UNIQUE KEY uq_p9_job_workspace_id(workspace_id,id)
SQL),
            new SqlMigrationStep(new MigrationStepId('002_scan_results'), 'Record immutable server-authoritative scanner outcomes.', <<<'SQL'
CREATE TABLE media_scan_results (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL, scan_job_id BIGINT UNSIGNED NULL,
 result_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, engine_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 engine_version VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, safe_detail_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 scanned_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_scan_asset(asset_id), KEY ix_p9_scan_workspace(workspace_id,scanned_at,id),
 CONSTRAINT fk_p9_scan_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p9_scan_job FOREIGN KEY(workspace_id,scan_job_id) REFERENCES media_processing_jobs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p9_scan_result CHECK(result_code IN ('CLEAN','INFECTED','ERROR','UNSUPPORTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_events'), 'Record append-only P9 lifecycle evidence.', <<<'SQL'
CREATE TABLE media_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL,
 safe_metadata_json JSON NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), KEY ix_p9_event_asset(asset_id,created_at,id),
 CONSTRAINT fk_p9_event_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p9_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_holds'), 'Record append-only P9 evidence holds.', <<<'SQL'
CREATE TABLE media_holds (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
 hold_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, placed_by_account_id BIGINT UNSIGNED NOT NULL,
 released_by_account_id BIGINT UNSIGNED NULL, released_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), KEY ix_p9_hold_active(asset_id,released_at,id),
 CONSTRAINT fk_p9_hold_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p9_hold_placer FOREIGN KEY(placed_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p9_hold_releaser FOREIGN KEY(released_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p9_hold CHECK(hold_code IN ('TECHNICAL','SECURITY','CONSENT','RIGHTS','GOVERNANCE') AND ((released_at IS NULL AND released_by_account_id IS NULL) OR (released_at IS NOT NULL AND released_by_account_id IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_delivery_policy'), 'Persist private-by-default media delivery policy.', <<<'SQL'
CREATE TABLE media_delivery_policies (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
 visibility_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PRIVATE', rights_granted TINYINT NOT NULL DEFAULT 0,
 consent_granted TINYINT NOT NULL DEFAULT 0, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_delivery_policy(asset_id),
 CONSTRAINT fk_p9_delivery_policy_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p9_delivery_policy CHECK(visibility_code IN ('PRIVATE','PUBLIC') AND rights_granted IN (0,1) AND consent_granted IN (0,1) AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_event_immutability'), 'Forbid mutation of P9 lifecycle evidence.', <<<'SQL'
CREATE TRIGGER trg_p9_media_events_no_update BEFORE UPDATE ON media_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P9 media events are append-only'
SQL),
            new SqlMigrationStep(new MigrationStepId('007_event_delete_immutability'), 'Forbid deletion of P9 lifecycle evidence.', <<<'SQL'
CREATE TRIGGER trg_p9_media_events_no_delete BEFORE DELETE ON media_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P9 media events are append-only'
SQL),
            new SqlMigrationStep(new MigrationStepId('008_variant_immutability'), 'Forbid mutation of immutable P9 variants.', <<<'SQL'
CREATE TRIGGER trg_p9_media_variants_no_update BEFORE UPDATE ON media_variants FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P9 media variants are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('009_variant_delete_immutability'), 'Forbid deletion of immutable P9 variants.', <<<'SQL'
CREATE TRIGGER trg_p9_media_variants_no_delete BEFORE DELETE ON media_variants FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P9 media variants are immutable'
SQL),
        ];
    }
}
