<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateQuranSearchCorpusMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911103000_create_quran_search_corpus');
    }
    public function description(): string
    {
        return 'Create immutable Simple Clean Qur’an search corpus overlay.';
    }
    public function dependencies(): array
    {
        return [(new EnableQuranSystemBaselineActorMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_corpora'), 'Create search-corpus lifecycle records.', <<<'SQL'
CREATE TABLE quran_search_corpora (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, canonical_release_id BIGINT UNSIGNED NOT NULL, search_source_artifact_id BIGINT UNSIGNED NOT NULL, corpus_code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, corpus_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, ayah_count SMALLINT UNSIGNED NOT NULL, simple_text_sha256 BINARY(32) NOT NULL, alignment_sha256 BINARY(32) NOT NULL, normalization_policy_version VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, import_tool_version VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, imported_at DATETIME(6) NOT NULL, validated_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL, rejected_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, version INT UNSIGNED NOT NULL, active_corpus_marker TINYINT GENERATED ALWAYS AS (IF(status = 'ACTIVE', 1, NULL)) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_quran_search_corpora_public_id(public_id), UNIQUE KEY uq_quran_search_corpora_code(corpus_code), UNIQUE KEY uq_quran_search_corpora_release_version(canonical_release_id, corpus_version), UNIQUE KEY uq_quran_search_corpora_active(canonical_release_id, active_corpus_marker), KEY ix_quran_search_corpora_release_status(canonical_release_id,status,id), KEY ix_quran_search_corpora_status_active(status,activated_at,id), KEY ix_quran_search_corpora_artifact_status(search_source_artifact_id,status,id), CONSTRAINT fk_quran_search_corpora_release FOREIGN KEY(canonical_release_id) REFERENCES quran_reference_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_search_corpora_artifact FOREIGN KEY(search_source_artifact_id) REFERENCES quran_source_artifacts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_search_corpora_status CHECK(status IN ('STAGED','VALIDATED','ACTIVE','RETIRED','REJECTED')), CONSTRAINT ck_quran_search_corpora_counts CHECK(ayah_count >= 1 AND version >= 1), CONSTRAINT ck_quran_search_corpora_dates CHECK((status <> 'VALIDATED' OR validated_at IS NOT NULL) AND (status <> 'ACTIVE' OR (validated_at IS NOT NULL AND activated_at IS NOT NULL)) AND (status <> 'RETIRED' OR retired_at IS NOT NULL) AND (status <> 'REJECTED' OR rejected_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_texts'), 'Create immutable release-aligned search text.', <<<'SQL'
CREATE TABLE quran_ayah_search_texts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, corpus_id BIGINT UNSIGNED NOT NULL, canonical_release_id BIGINT UNSIGNED NOT NULL, ayah_id BIGINT UNSIGNED NOT NULL, surah_number SMALLINT UNSIGNED NOT NULL, ayah_number SMALLINT UNSIGNED NOT NULL, global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, simple_clean_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL, text_byte_size SMALLINT UNSIGNED NOT NULL, text_sha256 BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_quran_search_texts_public_id(public_id), UNIQUE KEY uq_quran_search_texts_corpus_ayah(corpus_id,ayah_id), UNIQUE KEY uq_quran_search_texts_corpus_identity(corpus_id,surah_number,ayah_number), UNIQUE KEY uq_quran_search_texts_corpus_ordinal(corpus_id,global_ayah_ordinal), KEY ix_quran_search_texts_corpus_ordinal(corpus_id,global_ayah_ordinal,id), KEY ix_quran_search_texts_corpus_identity_order(corpus_id,surah_number,ayah_number,id), KEY ix_quran_search_texts_release_identity(canonical_release_id,surah_number,ayah_number,id), CONSTRAINT fk_quran_search_texts_corpus FOREIGN KEY(corpus_id) REFERENCES quran_search_corpora(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_search_texts_release FOREIGN KEY(canonical_release_id) REFERENCES quran_reference_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_search_texts_ayah FOREIGN KEY(ayah_id) REFERENCES quran_ayahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_search_texts_bounds CHECK(text_byte_size >= 1 AND surah_number >= 1 AND ayah_number >= 1 AND global_ayah_ordinal >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_validations'), 'Create immutable corpus validation evidence.', "CREATE TABLE quran_search_corpus_validations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, corpus_id BIGINT UNSIGNED NOT NULL, validator_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, validator_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, result VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_summary_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, evidence_sha256 BINARY(32) NOT NULL, executed_by_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, executed_by_account_id BIGINT UNSIGNED NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_quran_search_validations_public(public_id), KEY ix_quran_search_validations_corpus(corpus_id,occurred_at,id), CONSTRAINT fk_quran_search_validations_corpus FOREIGN KEY(corpus_id) REFERENCES quran_search_corpora(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_search_validations_account FOREIGN KEY(executed_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_search_validations_result CHECK(result IN ('PASS','FAIL')), CONSTRAINT ck_quran_search_validations_actor CHECK((executed_by_type='SYSTEM' AND executed_by_account_id IS NULL) OR (executed_by_type='ACCOUNT' AND executed_by_account_id IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('004_events'), 'Create immutable corpus lifecycle events.', "CREATE TABLE quran_search_corpus_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, corpus_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, from_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_quran_search_events_public(public_id), KEY ix_quran_search_events_corpus(corpus_id,occurred_at,id), CONSTRAINT fk_quran_search_events_corpus FOREIGN KEY(corpus_id) REFERENCES quran_search_corpora(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_quran_search_events_account FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_quran_search_events_type CHECK(event_type IN ('CREATED','IMPORTED','VALIDATED','ACTIVATED','RETIRED','REJECTED')), CONSTRAINT ck_quran_search_events_actor CHECK((actor_type='SYSTEM' AND actor_account_id IS NULL) OR (actor_type='ACCOUNT' AND actor_account_id IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('005_texts_no_update'), 'Prevent search text updates.', "CREATE TRIGGER trg_quran_ayah_search_texts_no_update BEFORE UPDATE ON quran_ayah_search_texts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search text is immutable'"),
            new SqlMigrationStep(new MigrationStepId('006_texts_no_delete'), 'Prevent search text deletion.', "CREATE TRIGGER trg_quran_ayah_search_texts_no_delete BEFORE DELETE ON quran_ayah_search_texts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search text is immutable'"),
            new SqlMigrationStep(new MigrationStepId('007_validation_no_update'), 'Prevent validation evidence updates.', "CREATE TRIGGER trg_quran_search_validations_no_update BEFORE UPDATE ON quran_search_corpus_validations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search validation is immutable'"),
            new SqlMigrationStep(new MigrationStepId('008_validation_no_delete'), 'Prevent validation evidence deletion.', "CREATE TRIGGER trg_quran_search_validations_no_delete BEFORE DELETE ON quran_search_corpus_validations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search validation is immutable'"),
            new SqlMigrationStep(new MigrationStepId('009_events_no_update'), 'Prevent corpus event updates.', "CREATE TRIGGER trg_quran_search_events_no_update BEFORE UPDATE ON quran_search_corpus_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search event is immutable'"),
            new SqlMigrationStep(new MigrationStepId('010_events_no_delete'), 'Prevent corpus event deletion.', "CREATE TRIGGER trg_quran_search_events_no_delete BEFORE DELETE ON quran_search_corpus_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran search event is immutable'"),
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
