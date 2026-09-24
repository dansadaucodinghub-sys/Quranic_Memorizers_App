<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Modules\MediaModeration\Infrastructure\Migration\CreateMediaConsentReviewMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** P10 records refer to P9 media and P4 canonical references; no media bytes are stored here. */
final readonly class CreateRecitationClipFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922100000_create_recitation_clip_foundation');
    }

    public function description(): string
    {
        return 'Create tenant-bound Recitation Clips and immutable public projection history.';
    }

    public function dependencies(): array
    {
        return [(new CreateMediaConsentReviewMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_variant_candidate'), 'Add a tenant-safe P9 variant candidate key.', <<<'SQL'
ALTER TABLE media_variants ADD UNIQUE KEY uq_p10_variant_workspace_asset_id(workspace_id,asset_id,id)
SQL),
            new SqlMigrationStep(new MigrationStepId('002_clips'), 'Create private-by-default tenant Recitation Clips.', <<<'SQL'
CREATE TABLE recitation_clips (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL,
 creator_account_id BIGINT UNSIGNED NOT NULL,
 creator_person_id BIGINT UNSIGNED NOT NULL,
 media_asset_id BIGINT UNSIGNED NOT NULL,
 media_variant_id BIGINT UNSIGNED NOT NULL,
 caption VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
 caption_language VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'en',
 status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT',
 audience VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PRIVATE',
 comment_policy VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DISABLED',
 publication_policy_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 supersedes_clip_id BIGINT UNSIGNED NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 submitted_at DATETIME(6) NULL,
 published_at DATETIME(6) NULL,
 hidden_at DATETIME(6) NULL,
 removed_at DATETIME(6) NULL,
 archived_at DATETIME(6) NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_p10_clip_public(public_id),
 UNIQUE KEY uq_p10_clip_workspace_id(workspace_id,id),
 KEY ix_p10_clip_workspace_status(workspace_id,status,created_at,id),
 KEY ix_p10_clip_creator(workspace_id,creator_account_id,created_at,id),
 KEY ix_p10_clip_media(workspace_id,media_asset_id,media_variant_id),
 CONSTRAINT fk_p10_clip_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_creator FOREIGN KEY(creator_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_person FOREIGN KEY(creator_person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_asset FOREIGN KEY(workspace_id,media_asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_variant FOREIGN KEY(workspace_id,media_asset_id,media_variant_id) REFERENCES media_variants(workspace_id,asset_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_supersedes FOREIGN KEY(workspace_id,supersedes_clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_clip_status CHECK(status IN ('DRAFT','REVIEW_PENDING','PUBLISHED','HIDDEN','REMOVED','SUPERSEDED','ARCHIVED')),
 CONSTRAINT ck_p10_clip_audience CHECK(audience IN ('PRIVATE','FOLLOWERS','PUBLIC')),
 CONSTRAINT ck_p10_clip_comments CHECK(comment_policy IN ('DISABLED','REVIEW','ENABLED')),
 CONSTRAINT ck_p10_clip_version CHECK(version>=1),
 CONSTRAINT ck_p10_clip_public CHECK(status<>'PUBLISHED' OR (audience<>'PRIVATE' AND published_at IS NOT NULL AND publication_policy_version IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_quran_references'), 'Bind Clips to canonical P4 release content.', <<<'SQL'
CREATE TABLE recitation_clip_quran_references (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 workspace_id BIGINT UNSIGNED NOT NULL,
 clip_id BIGINT UNSIGNED NOT NULL,
 reference_order SMALLINT UNSIGNED NOT NULL,
 release_id BIGINT UNSIGNED NOT NULL,
 surah_id BIGINT UNSIGNED NOT NULL,
 start_ayah_id BIGINT UNSIGNED NOT NULL,
 end_ayah_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_p10_clip_reference_order(clip_id,reference_order),
 KEY ix_p10_clip_reference_release(release_id,surah_id,start_ayah_id),
 CONSTRAINT fk_p10_clip_ref_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_ref_release FOREIGN KEY(release_id) REFERENCES quran_reference_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_ref_surah FOREIGN KEY(surah_id) REFERENCES quran_surahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_ref_start FOREIGN KEY(start_ayah_id) REFERENCES quran_ayahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_ref_end FOREIGN KEY(end_ayah_id) REFERENCES quran_ayahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_clip_ref_order CHECK(reference_order BETWEEN 1 AND 16)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_clip_events'), 'Preserve append-only Clip lifecycle events.', <<<'SQL'
CREATE TABLE recitation_clip_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL,
 clip_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 from_status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
 to_status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
 clip_version INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_p10_clip_event_public(public_id),
 UNIQUE KEY uq_p10_clip_event_version(clip_id,clip_version),
 KEY ix_p10_clip_event_time(workspace_id,clip_id,created_at,id),
 CONSTRAINT fk_p10_clip_event_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_clip_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_clip_event_version CHECK(clip_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_public_snapshots'), 'Preserve immutable public-safe Clip projections.', <<<'SQL'
CREATE TABLE recitation_clip_public_snapshots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL,
 clip_id BIGINT UNSIGNED NOT NULL,
 clip_version INT UNSIGNED NOT NULL,
 creator_public_id BINARY(16) NOT NULL,
 media_variant_public_id BINARY(16) NOT NULL,
 caption VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
 caption_language VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 surah_number SMALLINT UNSIGNED NOT NULL,
 start_ayah_number SMALLINT UNSIGNED NOT NULL,
 end_ayah_number SMALLINT UNSIGNED NOT NULL,
 checksum BINARY(32) NOT NULL,
 published_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_p10_snapshot_public(public_id),
 UNIQUE KEY uq_p10_snapshot_clip_version(clip_id,clip_version),
 KEY ix_p10_snapshot_published(published_at,id),
 CONSTRAINT fk_p10_snapshot_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_snapshot_version CHECK(clip_version>=1 AND surah_number>=1 AND start_ayah_number>=1 AND end_ayah_number>=start_ayah_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_event_update_guard'), 'Forbid Clip event updates.', "CREATE TRIGGER trg_p10_clip_event_no_update BEFORE UPDATE ON recitation_clip_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 Clip events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('007_event_delete_guard'), 'Forbid Clip event deletion.', "CREATE TRIGGER trg_p10_clip_event_no_delete BEFORE DELETE ON recitation_clip_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 Clip events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('008_snapshot_update_guard'), 'Forbid public snapshot updates.', "CREATE TRIGGER trg_p10_clip_snapshot_no_update BEFORE UPDATE ON recitation_clip_public_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 public snapshots are immutable'"),
            new SqlMigrationStep(new MigrationStepId('009_snapshot_delete_guard'), 'Forbid public snapshot deletion.', "CREATE TRIGGER trg_p10_clip_snapshot_no_delete BEFORE DELETE ON recitation_clip_public_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 public snapshots are immutable'"),
        ];
    }
}
