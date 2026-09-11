<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateQuranCanonicalContentMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911080100_create_quran_canonical_content');
    }

    public function description(): string
    {
        return 'Create immutable release-scoped canonical Qur’an content and structure.';
    }

    public function dependencies(): array
    {
        return [(new CompleteQuranReleaseManifestGovernanceMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_surahs'), 'Create immutable release-scoped Surah metadata.', <<<'SQL'
CREATE TABLE quran_surahs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, metadata_source_artifact_id BIGINT UNSIGNED NOT NULL, surah_number SMALLINT UNSIGNED NOT NULL, ayah_count SMALLINT UNSIGNED NOT NULL, first_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, last_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, revelation_order SMALLINT UNSIGNED NULL, revelation_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL, ruku_count SMALLINT UNSIGNED NULL, arabic_name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL, transliterated_name VARCHAR(128) NULL, english_name VARCHAR(128) NULL, metadata_sha256 BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_surahs_public_id (public_id), UNIQUE KEY uq_quran_surahs_release_number (release_id, surah_number), UNIQUE KEY uq_quran_surahs_release_number_id (release_id, surah_number, id), KEY ix_quran_surahs_release_number_id (release_id, surah_number, id), KEY ix_quran_surahs_release_revelation_order (release_id, revelation_order, id), KEY ix_quran_surahs_release_revelation_type (release_id, revelation_type, surah_number, id), CONSTRAINT fk_quran_surahs_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_surahs_metadata_artifact FOREIGN KEY (metadata_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_surahs_bounds CHECK (surah_number >= 1 AND ayah_count >= 1 AND first_global_ayah_ordinal >= 1 AND last_global_ayah_ordinal >= first_global_ayah_ordinal AND last_global_ayah_ordinal - first_global_ayah_ordinal + 1 = ayah_count), CONSTRAINT ck_quran_surahs_revelation_order CHECK (revelation_order IS NULL OR revelation_order >= 1), CONSTRAINT ck_quran_surahs_ruku_count CHECK (ruku_count IS NULL OR ruku_count >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_ayahs'), 'Create immutable canonical Uthmani Ayat.', <<<'SQL'
CREATE TABLE quran_ayahs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, surah_id BIGINT UNSIGNED NOT NULL, canonical_text_source_artifact_id BIGINT UNSIGNED NOT NULL, surah_number SMALLINT UNSIGNED NOT NULL, ayah_number SMALLINT UNSIGNED NOT NULL, global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, canonical_uthmani_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL, text_byte_size SMALLINT UNSIGNED NOT NULL, text_sha256 BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_ayahs_public_id (public_id), UNIQUE KEY uq_quran_ayahs_release_identity (release_id, surah_number, ayah_number), UNIQUE KEY uq_quran_ayahs_release_ordinal (release_id, global_ayah_ordinal), UNIQUE KEY uq_quran_ayahs_release_number_id (release_id, surah_number, ayah_number, id), KEY ix_quran_ayahs_release_number_id (release_id, surah_number, ayah_number, id), KEY ix_quran_ayahs_release_ordinal_id (release_id, global_ayah_ordinal, id), KEY ix_quran_ayahs_surah_number_id (surah_id, ayah_number, id), CONSTRAINT fk_quran_ayahs_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_ayahs_canonical_artifact FOREIGN KEY (canonical_text_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_ayahs_surah_release FOREIGN KEY (release_id, surah_number, surah_id) REFERENCES quran_surahs (release_id, surah_number, id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_ayahs_bounds CHECK (surah_number >= 1 AND ayah_number >= 1 AND global_ayah_ordinal >= 1 AND text_byte_size >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_partitions'), 'Create immutable source-supported Qur’an partitions.', <<<'SQL'
CREATE TABLE quran_partitions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, metadata_source_artifact_id BIGINT UNSIGNED NOT NULL, partition_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, partition_number SMALLINT UNSIGNED NOT NULL, parent_partition_id BIGINT UNSIGNED NULL, start_ayah_id BIGINT UNSIGNED NOT NULL, end_ayah_id BIGINT UNSIGNED NOT NULL, start_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, end_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, source_index SMALLINT UNSIGNED NOT NULL, derivation_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_partitions_public_id (public_id), UNIQUE KEY uq_quran_partitions_release_type_number (release_id, partition_type, partition_number), UNIQUE KEY uq_quran_partitions_release_id (release_id, id), UNIQUE KEY uq_quran_partitions_release_type_source (release_id, partition_type, source_index), KEY ix_quran_partitions_release_type_number (release_id, partition_type, partition_number, id), KEY ix_quran_partitions_release_range (release_id, start_global_ayah_ordinal, end_global_ayah_ordinal, id), KEY ix_quran_partitions_start_type (start_ayah_id, partition_type, id), CONSTRAINT fk_quran_partitions_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_partitions_metadata_artifact FOREIGN KEY (metadata_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_partitions_parent FOREIGN KEY (release_id, parent_partition_id) REFERENCES quran_partitions (release_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_partitions_start FOREIGN KEY (start_ayah_id) REFERENCES quran_ayahs (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_partitions_end FOREIGN KEY (end_ayah_id) REFERENCES quran_ayahs (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_partitions_type CHECK (partition_type IN ('JUZ','HIZB','HIZB_QUARTER','MANZIL','RUKU','MUSHAF_PAGE')), CONSTRAINT ck_quran_partitions_bounds CHECK (partition_number >= 1 AND source_index >= 1 AND start_global_ayah_ordinal >= 1 AND end_global_ayah_ordinal >= start_global_ayah_ordinal), CONSTRAINT ck_quran_partitions_derivation CHECK (derivation_type IN ('SOURCE_MARKER_RANGE','DERIVED_FROM_SOURCE_MARKERS'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_sajdah'), 'Create immutable source-provided Sajdah markers.', <<<'SQL'
CREATE TABLE quran_sajdah_markers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, metadata_source_artifact_id BIGINT UNSIGNED NOT NULL, sajdah_index SMALLINT UNSIGNED NOT NULL, ayah_id BIGINT UNSIGNED NOT NULL, surah_number SMALLINT UNSIGNED NOT NULL, ayah_number SMALLINT UNSIGNED NOT NULL, global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, source_sajdah_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_sajdah_public_id (public_id), UNIQUE KEY uq_quran_sajdah_release_index (release_id, sajdah_index), UNIQUE KEY uq_quran_sajdah_release_ayah (release_id, ayah_id), CONSTRAINT fk_quran_sajdah_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_sajdah_metadata_artifact FOREIGN KEY (metadata_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_sajdah_ayah FOREIGN KEY (ayah_id) REFERENCES quran_ayahs (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_sajdah_bounds CHECK (sajdah_index >= 1 AND surah_number >= 1 AND ayah_number >= 1 AND global_ayah_ordinal >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_summary'), 'Create immutable Qur’an release content summaries.', <<<'SQL'
CREATE TABLE quran_release_content_summaries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, release_id BIGINT UNSIGNED NOT NULL, canonical_text_source_artifact_id BIGINT UNSIGNED NOT NULL, metadata_source_artifact_id BIGINT UNSIGNED NOT NULL, surah_count SMALLINT UNSIGNED NOT NULL, ayah_count SMALLINT UNSIGNED NOT NULL, juz_count SMALLINT UNSIGNED NOT NULL, hizb_count SMALLINT UNSIGNED NOT NULL, hizb_quarter_count SMALLINT UNSIGNED NOT NULL, manzil_count SMALLINT UNSIGNED NOT NULL, ruku_count SMALLINT UNSIGNED NOT NULL, mushaf_page_count SMALLINT UNSIGNED NOT NULL, sajdah_count SMALLINT UNSIGNED NOT NULL, canonical_text_sha256 BINARY(32) NOT NULL, structure_sha256 BINARY(32) NOT NULL, combined_content_sha256 BINARY(32) NOT NULL, canonical_serialization_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, validation_policy_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, import_tool_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, imported_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_quran_summaries_public_id (public_id), UNIQUE KEY uq_quran_summaries_release (release_id), CONSTRAINT fk_quran_summaries_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_summaries_text_artifact FOREIGN KEY (canonical_text_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_summaries_metadata_artifact FOREIGN KEY (metadata_source_artifact_id) REFERENCES quran_source_artifacts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_summaries_counts CHECK (surah_count >= 1 AND ayah_count >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            ...$this->immutabilityTriggers('quran_surahs', 'surahs', 6),
            ...$this->immutabilityTriggers('quran_ayahs', 'ayahs', 8),
            ...$this->immutabilityTriggers('quran_partitions', 'partitions', 10),
            ...$this->immutabilityTriggers('quran_sajdah_markers', 'sajdah', 12),
            ...$this->immutabilityTriggers('quran_release_content_summaries', 'summaries', 14),
        ];
    }

    /** @return list<SqlMigrationStep> */
    private function immutabilityTriggers(string $table, string $name, int $sequence): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId(sprintf('%03d_%s_no_update', $sequence, $name)), 'Prevent immutable Qur’an content updates.', "CREATE TRIGGER trg_{$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran canonical content is immutable'"),
            new SqlMigrationStep(new MigrationStepId(sprintf('%03d_%s_no_delete', $sequence + 1, $name)), 'Prevent immutable Qur’an content deletion.', "CREATE TRIGGER trg_{$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran canonical content is immutable'"),
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
