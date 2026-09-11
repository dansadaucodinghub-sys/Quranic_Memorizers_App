<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateCompetitionCategoryConfigurationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911130100_create_competition_category_configuration');
    }
    public function description(): string
    {
        return 'Create category, immutable Qur’an segment, eligibility, window, capacity, snapshot, and event configuration.';
    }
    public function dependencies(): array
    {
        return [(new CreateCompetitionConfigurationMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_categories'), 'Create competition categories linked to immutable Qur’an releases.', <<<'SQL'
CREATE TABLE competition_categories (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, category_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, slug VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, name_en VARCHAR(200) NOT NULL, name_ar VARCHAR(200) NULL, description_en TEXT NULL, description_ar TEXT NULL, competition_mode VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, approval_mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT', quran_release_id BIGINT UNSIGNED NOT NULL, capacity INT UNSIGNED NOT NULL, waitlist_enabled TINYINT(1) NOT NULL DEFAULT 0, waitlist_capacity INT UNSIGNED NOT NULL DEFAULT 0, config_version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, published_at DATETIME(6) NULL, closed_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_categories_public(public_id), UNIQUE KEY uq_competition_categories_code(workspace_id,edition_id,category_code), UNIQUE KEY uq_competition_categories_slug(workspace_id,edition_id,slug), UNIQUE KEY uq_competition_categories_workspace_id(workspace_id,id), KEY ix_competition_categories_edition(workspace_id,edition_id,status,id), CONSTRAINT fk_competition_categories_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_categories_release FOREIGN KEY(quran_release_id) REFERENCES quran_reference_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_categories_mode CHECK(competition_mode IN ('MEMORIZATION','RECITATION','TAJWID','COMBINED','OTHER')), CONSTRAINT ck_competition_categories_approval CHECK(approval_mode IN ('MANUAL','AUTO_IF_ELIGIBLE')), CONSTRAINT ck_competition_categories_status CHECK(status IN ('DRAFT','PUBLISHED','CLOSED','CANCELLED')), CONSTRAINT ck_competition_categories_capacity CHECK(capacity>=1 AND waitlist_capacity>=0 AND (waitlist_enabled=1 OR waitlist_capacity=0)), CONSTRAINT ck_competition_categories_version CHECK(config_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_segments'), 'Create release-bound Qur’an Ayah-range category segments.', <<<'SQL'
CREATE TABLE competition_category_quran_segments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, quran_release_id BIGINT UNSIGNED NOT NULL, segment_order SMALLINT UNSIGNED NOT NULL, start_ayah_id BIGINT UNSIGNED NOT NULL, end_ayah_id BIGINT UNSIGNED NOT NULL, start_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, end_global_ayah_ordinal SMALLINT UNSIGNED NOT NULL, label_en VARCHAR(200) NULL, label_ar VARCHAR(200) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_segments_public(public_id), UNIQUE KEY uq_competition_segments_order(workspace_id,category_id,segment_order), KEY ix_competition_segments_range(category_id,start_global_ayah_ordinal,end_global_ayah_ordinal), CONSTRAINT fk_competition_segments_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_segments_release FOREIGN KEY(quran_release_id) REFERENCES quran_reference_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_segments_start FOREIGN KEY(start_ayah_id) REFERENCES quran_ayahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_segments_end FOREIGN KEY(end_ayah_id) REFERENCES quran_ayahs(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_segments_bounds CHECK(start_global_ayah_ordinal>=1 AND end_global_ayah_ordinal>=start_global_ayah_ordinal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_rules'), 'Create closed, versioned non-executable eligibility rules.', <<<'SQL'
CREATE TABLE competition_eligibility_rules (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, rule_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, rule_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, rule_version SMALLINT UNSIGNED NOT NULL, evaluation_mode VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, configuration_json JSON NOT NULL, configuration_sha256 BINARY(32) NOT NULL, required TINYINT(1) NOT NULL DEFAULT 1, display_order SMALLINT UNSIGNED NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE', created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_rules_public(public_id), UNIQUE KEY uq_competition_rules_code(workspace_id,category_id,rule_code), KEY ix_competition_rules_category(workspace_id,category_id,status,display_order,id), CONSTRAINT fk_competition_rules_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_rules_type CHECK(rule_type IN ('AGE_RANGE','RESIDENCY_SCOPE','ORGANIZATION_AFFILIATION','SCHOOL_AFFILIATION','PARTICIPANT_DIVISION','PROFILE_COMPLETENESS','GUARDIAN_CONSENT','MAX_CATEGORIES_PER_EDITION','CUSTOM_DECLARATION')), CONSTRAINT ck_competition_rules_mode CHECK(evaluation_mode IN ('AUTOMATIC','MANUAL')), CONSTRAINT ck_competition_rules_status CHECK(status IN ('ACTIVE','RETIRED')), CONSTRAINT ck_competition_rules_version CHECK(rule_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_windows'), 'Create one active UTC registration window per category.', <<<'SQL'
CREATE TABLE competition_registration_windows (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, opens_at DATETIME(6) NOT NULL, closes_at DATETIME(6) NOT NULL, late_closes_at DATETIME(6) NULL, timezone_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SCHEDULED', version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, active_marker TINYINT GENERATED ALWAYS AS(CASE WHEN status IN ('SCHEDULED','OPEN') THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_windows_public(public_id), UNIQUE KEY uq_competition_windows_active(workspace_id,category_id,active_marker), KEY ix_competition_windows_due(status,opens_at,closes_at,id), CONSTRAINT fk_competition_windows_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_windows_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_windows_status CHECK(status IN ('SCHEDULED','OPEN','CLOSED','CANCELLED')), CONSTRAINT ck_competition_windows_dates CHECK(opens_at<closes_at AND (late_closes_at IS NULL OR late_closes_at>=closes_at)), CONSTRAINT ck_competition_windows_version CHECK(version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_capacity'), 'Create lockable category capacity and waitlist counters.', <<<'SQL'
CREATE TABLE competition_category_capacity_states (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, capacity INT UNSIGNED NOT NULL, approved_count INT UNSIGNED NOT NULL DEFAULT 0, waitlist_count INT UNSIGNED NOT NULL DEFAULT 0, next_waitlist_sequence INT UNSIGNED NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_capacity_public(public_id), UNIQUE KEY uq_competition_capacity_category(workspace_id,category_id), CONSTRAINT fk_competition_capacity_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_capacity_counts CHECK(capacity>=1 AND approved_count>=0 AND approved_count<=capacity AND waitlist_count>=0 AND next_waitlist_sequence>=1 AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_snapshots'), 'Create immutable canonical edition configuration snapshots.', <<<'SQL'
CREATE TABLE competition_edition_configuration_snapshots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, revision_number INT UNSIGNED NOT NULL, snapshot_schema_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, canonical_json JSON NOT NULL, sha256 BINARY(32) NOT NULL, reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_by_account_id BIGINT UNSIGNED NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_snapshots_public(public_id), UNIQUE KEY uq_competition_snapshots_revision(workspace_id,edition_id,revision_number), UNIQUE KEY uq_competition_snapshots_workspace_id(workspace_id,id), CONSTRAINT fk_competition_snapshots_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_snapshots_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_snapshots_revision CHECK(revision_number>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('007_events'), 'Create append-only edition lifecycle events.', <<<'SQL'
CREATE TABLE competition_edition_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, from_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL, actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, configuration_snapshot_id BIGINT UNSIGNED NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_edition_events_public(public_id), KEY ix_competition_edition_events(workspace_id,edition_id,occurred_at,id), CONSTRAINT fk_competition_edition_events_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_edition_events_snapshot FOREIGN KEY(workspace_id,configuration_snapshot_id) REFERENCES competition_edition_configuration_snapshots(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_edition_events_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_edition_events_type CHECK(event_type IN ('CREATED','PUBLISHED','REGISTRATION_OPENED','REGISTRATION_CLOSED','REGISTRATION_REOPENED','CAPACITY_CHANGED','REGISTRATION_DEADLINE_EXTENDED','ROSTER_FINALIZED','CANCELLED')), CONSTRAINT ck_competition_edition_events_actor CHECK(actor_type IN ('ACCOUNT','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('008_snapshot_immutability_update'), 'Prevent configuration snapshot updates.', "CREATE TRIGGER trg_competition_snapshots_no_update BEFORE UPDATE ON competition_edition_configuration_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition snapshots are append-only'"),
            new SqlMigrationStep(new MigrationStepId('009_snapshot_immutability_delete'), 'Prevent configuration snapshot deletion.', "CREATE TRIGGER trg_competition_snapshots_no_delete BEFORE DELETE ON competition_edition_configuration_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition snapshots are append-only'"),
            new SqlMigrationStep(new MigrationStepId('010_event_immutability_update'), 'Prevent edition event updates.', "CREATE TRIGGER trg_competition_edition_events_no_update BEFORE UPDATE ON competition_edition_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition edition events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('011_event_immutability_delete'), 'Prevent edition event deletion.', "CREATE TRIGGER trg_competition_edition_events_no_delete BEFORE DELETE ON competition_edition_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition edition events are append-only'"),
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
