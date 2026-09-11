<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionRegistration\Infrastructure\Migration;

use Qmdb\Modules\CompetitionConfiguration\Infrastructure\Migration\CreateCompetitionCategoryConfigurationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateCompetitionRegistrationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911130200_create_competition_registration');
    }
    public function description(): string
    {
        return 'Create workspace-safe competition registration, evidence, review, and immutable roster records.';
    }
    public function dependencies(): array
    {
        return [(new CreateCompetitionCategoryConfigurationMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_registrations'), 'Create competition registration aggregate records.', <<<'SQL'
CREATE TABLE competition_registrations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, competitor_person_id BIGINT UNSIGNED NOT NULL, applicant_account_id BIGINT UNSIGNED NOT NULL, actor_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_relationship_id BIGINT UNSIGNED NULL, registration_number VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, eligibility_outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, waitlist_sequence INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1, submitted_at DATETIME(6) NOT NULL, review_started_at DATETIME(6) NULL, decided_at DATETIME(6) NULL, withdrawn_at DATETIME(6) NULL, rostered_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_registrations_public(public_id), UNIQUE KEY uq_competition_registrations_person_category(workspace_id,category_id,competitor_person_id), UNIQUE KEY uq_competition_registrations_number(workspace_id,registration_number), UNIQUE KEY uq_competition_registrations_workspace_id(workspace_id,id), KEY ix_competition_registrations_category(workspace_id,edition_id,category_id,status,submitted_at,id), KEY ix_competition_registrations_waitlist(workspace_id,category_id,status,waitlist_sequence,id), KEY ix_competition_registrations_applicant(applicant_account_id,submitted_at,id), KEY ix_competition_registrations_competitor(competitor_person_id,submitted_at,id),
 CONSTRAINT fk_competition_registrations_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_registrations_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_registrations_person FOREIGN KEY(competitor_person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_registrations_applicant FOREIGN KEY(applicant_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_registrations_actor CHECK(actor_type IN ('SELF','GUARDIAN','ORGANIZATION_REPRESENTATIVE','REGISTRAR')), CONSTRAINT ck_competition_registrations_status CHECK(status IN ('SUBMITTED','UNDER_REVIEW','APPROVED','WAITLISTED','REJECTED','WITHDRAWN','ROSTERED','CANCELLED')), CONSTRAINT ck_competition_registrations_outcome CHECK(eligibility_outcome IN ('ELIGIBLE','INELIGIBLE','MANUAL_REVIEW')), CONSTRAINT ck_competition_registrations_waitlist CHECK((status='WAITLISTED' AND waitlist_sequence IS NOT NULL) OR (status<>'WAITLISTED' AND waitlist_sequence IS NULL)), CONSTRAINT ck_competition_registrations_version CHECK(version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_evidence'), 'Create append-only safe eligibility evidence.', <<<'SQL'
CREATE TABLE competition_registration_eligibility_evidence (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, registration_id BIGINT UNSIGNED NOT NULL, eligibility_rule_id BIGINT UNSIGNED NOT NULL, evaluator_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, evaluator_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, evidence_sha256 BINARY(32) NOT NULL, evidence_ciphertext MEDIUMBLOB NULL, evidence_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, evaluated_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_eligibility_evidence_public(public_id), KEY ix_competition_eligibility_evidence_registration(workspace_id,registration_id,id), CONSTRAINT fk_competition_eligibility_evidence_registration FOREIGN KEY(workspace_id,registration_id) REFERENCES competition_registrations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_eligibility_evidence_rule FOREIGN KEY(eligibility_rule_id) REFERENCES competition_eligibility_rules(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_eligibility_evidence_outcome CHECK(outcome IN ('PASS','FAIL','MANUAL_REVIEW')), CONSTRAINT ck_competition_eligibility_evidence_ciphertext CHECK((evidence_ciphertext IS NULL AND evidence_key_id IS NULL) OR (evidence_ciphertext IS NOT NULL AND evidence_key_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_consents'), 'Create append-only explicit consent events.', <<<'SQL'
CREATE TABLE competition_registration_consents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, registration_id BIGINT UNSIGNED NOT NULL, consent_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, consent_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, consent_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, action VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, related_person_id BIGINT UNSIGNED NULL, evidence_sha256 BINARY(32) NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_consents_public(public_id), KEY ix_competition_consents_registration(workspace_id,registration_id,occurred_at,id), CONSTRAINT fk_competition_consents_registration FOREIGN KEY(workspace_id,registration_id) REFERENCES competition_registrations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_consents_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_consents_person FOREIGN KEY(related_person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_consents_type CHECK(consent_type IN ('TERMS','PRIVACY','GUARDIAN','CUSTOM_DECLARATION')), CONSTRAINT ck_competition_consents_action CHECK(action IN ('GRANTED','REVOKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_reviews'), 'Create append-only reviewer decisions.', <<<'SQL'
CREATE TABLE competition_registration_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, registration_id BIGINT UNSIGNED NOT NULL, reviewer_account_id BIGINT UNSIGNED NOT NULL, decision VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, note_ciphertext MEDIUMBLOB NULL, note_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, expected_registration_version INT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_reviews_public(public_id), KEY ix_competition_reviews_registration(workspace_id,registration_id,occurred_at,id), CONSTRAINT fk_competition_reviews_registration FOREIGN KEY(workspace_id,registration_id) REFERENCES competition_registrations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_reviews_reviewer FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_reviews_decision CHECK(decision IN ('START_REVIEW','APPROVE','WAITLIST','REJECT','PROMOTE_FROM_WAITLIST','CANCEL')), CONSTRAINT ck_competition_reviews_note CHECK((note_ciphertext IS NULL AND note_key_id IS NULL) OR (note_ciphertext IS NOT NULL AND note_key_id IS NOT NULL)), CONSTRAINT ck_competition_reviews_version CHECK(expected_registration_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_events'), 'Create append-only registration lifecycle events.', <<<'SQL'
CREATE TABLE competition_registration_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, registration_id BIGINT UNSIGNED NOT NULL, event_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, from_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, eligibility_evidence_set_sha256 BINARY(32) NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_registration_events_public(public_id), KEY ix_competition_registration_events(workspace_id,registration_id,occurred_at,id), CONSTRAINT fk_competition_registration_events_registration FOREIGN KEY(workspace_id,registration_id) REFERENCES competition_registrations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_registration_events_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_registration_events_type CHECK(event_type IN ('SUBMITTED','REVIEW_STARTED','APPROVED','WAITLISTED','REJECTED','WITHDRAWN','PROMOTED','ROSTERED','CANCELLED')), CONSTRAINT ck_competition_registration_events_actor CHECK(actor_type IN ('SELF','GUARDIAN','ORGANIZATION_REPRESENTATIVE','REGISTRAR','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_rosters'), 'Create draft and finalized category rosters.', <<<'SQL'
CREATE TABLE competition_rosters (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT', entry_count INT UNSIGNED NOT NULL DEFAULT 0, snapshot_schema_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, snapshot_sha256 BINARY(32) NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, finalized_by_account_id BIGINT UNSIGNED NULL, finalized_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_rosters_public(public_id), UNIQUE KEY uq_competition_rosters_category(workspace_id,category_id), UNIQUE KEY uq_competition_rosters_workspace_id(workspace_id,id), KEY ix_competition_rosters_edition(workspace_id,edition_id,status,id), CONSTRAINT fk_competition_rosters_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_rosters_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_rosters_finalizer FOREIGN KEY(finalized_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_rosters_status CHECK(status IN ('DRAFT','FINALIZED')), CONSTRAINT ck_competition_rosters_count CHECK(entry_count>=0 AND version>=1), CONSTRAINT ck_competition_rosters_finalized CHECK((status='DRAFT' AND finalized_by_account_id IS NULL AND finalized_at IS NULL) OR (status='FINALIZED' AND finalized_by_account_id IS NOT NULL AND finalized_at IS NOT NULL AND snapshot_sha256 IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('007_roster_entries'), 'Create deterministic roster entries without duplicating PII.', <<<'SQL'
CREATE TABLE competition_roster_entries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, roster_id BIGINT UNSIGNED NOT NULL, registration_id BIGINT UNSIGNED NOT NULL, competitor_person_id BIGINT UNSIGNED NOT NULL, roster_sequence INT UNSIGNED NOT NULL, registration_number VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_roster_entries_public(public_id), UNIQUE KEY uq_competition_roster_entries_registration(workspace_id,roster_id,registration_id), UNIQUE KEY uq_competition_roster_entries_person(workspace_id,roster_id,competitor_person_id), UNIQUE KEY uq_competition_roster_entries_sequence(workspace_id,roster_id,roster_sequence), CONSTRAINT fk_competition_roster_entries_roster FOREIGN KEY(workspace_id,roster_id) REFERENCES competition_rosters(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_roster_entries_registration FOREIGN KEY(workspace_id,registration_id) REFERENCES competition_registrations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_roster_entries_person FOREIGN KEY(competitor_person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_roster_entries_sequence CHECK(roster_sequence>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('008_evidence_no_update'), 'Prevent eligibility evidence updates.', "CREATE TRIGGER trg_competition_evidence_no_update BEFORE UPDATE ON competition_registration_eligibility_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition eligibility evidence is append-only'"),
            new SqlMigrationStep(new MigrationStepId('009_evidence_no_delete'), 'Prevent eligibility evidence deletion.', "CREATE TRIGGER trg_competition_evidence_no_delete BEFORE DELETE ON competition_registration_eligibility_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition eligibility evidence is append-only'"),
            new SqlMigrationStep(new MigrationStepId('010_consents_no_update'), 'Prevent consent event updates.', "CREATE TRIGGER trg_competition_consents_no_update BEFORE UPDATE ON competition_registration_consents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition consents are append-only'"),
            new SqlMigrationStep(new MigrationStepId('011_consents_no_delete'), 'Prevent consent event deletion.', "CREATE TRIGGER trg_competition_consents_no_delete BEFORE DELETE ON competition_registration_consents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition consents are append-only'"),
            new SqlMigrationStep(new MigrationStepId('012_reviews_no_update'), 'Prevent review updates.', "CREATE TRIGGER trg_competition_reviews_no_update BEFORE UPDATE ON competition_registration_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition reviews are append-only'"),
            new SqlMigrationStep(new MigrationStepId('013_reviews_no_delete'), 'Prevent review deletion.', "CREATE TRIGGER trg_competition_reviews_no_delete BEFORE DELETE ON competition_registration_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition reviews are append-only'"),
            new SqlMigrationStep(new MigrationStepId('014_events_no_update'), 'Prevent registration event updates.', "CREATE TRIGGER trg_competition_registration_events_no_update BEFORE UPDATE ON competition_registration_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition registration events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('015_events_no_delete'), 'Prevent registration event deletion.', "CREATE TRIGGER trg_competition_registration_events_no_delete BEFORE DELETE ON competition_registration_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition registration events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('017_roster_entries_no_update'), 'Prevent finalized roster entry mutation.', "CREATE TRIGGER trg_competition_roster_entries_no_update BEFORE UPDATE ON competition_roster_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition roster entries are immutable'"),
            new SqlMigrationStep(new MigrationStepId('018_roster_entries_no_delete'), 'Prevent finalized roster entry deletion.', "CREATE TRIGGER trg_competition_roster_entries_no_delete BEFORE DELETE ON competition_roster_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Competition roster entries are immutable'"),
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
