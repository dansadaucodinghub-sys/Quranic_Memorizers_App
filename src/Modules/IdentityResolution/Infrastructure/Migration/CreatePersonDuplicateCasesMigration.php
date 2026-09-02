<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePersonDuplicateCasesMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902050300_create_person_duplicate_cases');
    }

    public function description(): string
    {
        return 'Create consent-gated duplicate Person case and history records.';
    }

    public function dependencies(): array
    {
        return [(new CreateProfileVerificationAssertionsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_duplicate_cases'), 'Create private duplicate-Person review cases.', <<<'SQL'
CREATE TABLE people_duplicate_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    first_person_id BIGINT UNSIGNED NOT NULL,
    second_person_id BIGINT UNSIGNED NOT NULL,
    reported_by_account_id BIGINT UNSIGNED NOT NULL,
    reporter_authority_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reporter_guardianship_id BIGINT UNSIGNED NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resolution_outcome VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
    conflict_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
    canonical_person_id BIGINT UNSIGNED NULL,
    duplicate_person_id BIGINT UNSIGNED NULL,
    reviewed_by_account_id BIGINT UNSIGNED NULL,
    review_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    review_justification VARCHAR(2000) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    reported_at DATETIME(6) NOT NULL,
    review_started_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    dismissed_at DATETIME(6) NULL,
    blocked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    open_pair_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('REPORTED','CONSENT_REQUIRED','READY_FOR_REVIEW','UNDER_REVIEW') THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_duplicate_cases_public_id (public_id),
    UNIQUE KEY uq_people_duplicate_cases_open_pair (first_person_id, second_person_id, open_pair_marker),
    KEY ix_people_duplicate_cases_status_reported (status, reported_at, id),
    KEY ix_people_duplicate_cases_first_status (first_person_id, status, id),
    KEY ix_people_duplicate_cases_second_status (second_person_id, status, id),
    KEY ix_people_duplicate_cases_reporter_status (reported_by_account_id, status, id),
    KEY ix_people_duplicate_cases_reviewer_resolved (reviewed_by_account_id, resolved_at, id),
    CONSTRAINT fk_people_duplicate_cases_first FOREIGN KEY (first_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_second FOREIGN KEY (second_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_reporter FOREIGN KEY (reported_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_guardianship FOREIGN KEY (reporter_guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_canonical FOREIGN KEY (canonical_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_duplicate FOREIGN KEY (duplicate_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_cases_reviewer FOREIGN KEY (reviewed_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_duplicate_cases_order CHECK (first_person_id < second_person_id),
    CONSTRAINT ck_people_duplicate_cases_status CHECK (status IN ('REPORTED','CONSENT_REQUIRED','READY_FOR_REVIEW','UNDER_REVIEW','DISMISSED','RESOLVED','BLOCKED')),
    CONSTRAINT ck_people_duplicate_cases_outcome CHECK (resolution_outcome IS NULL OR resolution_outcome IN ('NOT_DUPLICATE','CANONICALIZED','IDENTITY_LINK_CONFLICT','DEMOGRAPHIC_CONFLICT','GEOGRAPHY_CONFLICT','MEMORIZER_PROGRESS_CONFLICT','ORGANIZATION_AFFILIATION_CONFLICT','CONSENT_UNAVAILABLE','CONSENT_DECLINED','AFFECTED_RECORD_LIMIT_EXCEEDED')),
    CONSTRAINT ck_people_duplicate_cases_reporter_authority CHECK (reporter_authority_type IN ('SELF','GUARDIAN','PLATFORM_REVIEWER')),
    CONSTRAINT ck_people_duplicate_cases_version CHECK (version >= 1),
    CONSTRAINT ck_people_duplicate_cases_resolved CHECK (status <> 'RESOLVED' OR (resolved_at IS NOT NULL AND canonical_person_id IS NOT NULL AND duplicate_person_id IS NOT NULL AND resolution_outcome = 'CANONICALIZED')),
    CONSTRAINT ck_people_duplicate_cases_dismissed CHECK (status <> 'DISMISSED' OR (dismissed_at IS NOT NULL AND resolution_outcome = 'NOT_DUPLICATE')),
    CONSTRAINT ck_people_duplicate_cases_blocked CHECK (status <> 'BLOCKED' OR (blocked_at IS NOT NULL AND conflict_code IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_duplicate_consent_requirements'), 'Create immutable-authority duplicate-case consent requirements.', <<<'SQL'
CREATE TABLE people_duplicate_consent_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    authority_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authority_account_id BIGINT UNSIGNED NOT NULL,
    guardianship_id BIGINT UNSIGNED NULL,
    decision VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    responded_at DATETIME(6) NULL,
    invalidated_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    active_requirement_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN invalidated_at IS NULL THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_duplicate_consent_requirements_public_id (public_id),
    UNIQUE KEY uq_people_duplicate_consent_requirements_active_authority (case_id, person_id, authority_account_id, active_requirement_marker),
    KEY ix_people_duplicate_consent_requirements_case_decision (case_id, decision, id),
    KEY ix_people_duplicate_consent_requirements_authority_decision (authority_account_id, decision, created_at, id),
    KEY ix_people_duplicate_consent_requirements_person_decision (person_id, decision, id),
    CONSTRAINT fk_people_duplicate_consent_requirements_case FOREIGN KEY (case_id) REFERENCES people_duplicate_cases (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_consent_requirements_person FOREIGN KEY (person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_consent_requirements_authority FOREIGN KEY (authority_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_consent_requirements_guardianship FOREIGN KEY (guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_duplicate_consent_requirements_authority_type CHECK (authority_type IN ('SELF','GUARDIAN')),
    CONSTRAINT ck_people_duplicate_consent_requirements_decision CHECK (decision IN ('PENDING','APPROVED','DECLINED')),
    CONSTRAINT ck_people_duplicate_consent_requirements_version CHECK (version >= 1),
    CONSTRAINT ck_people_duplicate_consent_requirements_self CHECK (authority_type <> 'SELF' OR guardianship_id IS NULL),
    CONSTRAINT ck_people_duplicate_consent_requirements_guardian CHECK (authority_type <> 'GUARDIAN' OR guardianship_id IS NOT NULL),
    CONSTRAINT ck_people_duplicate_consent_requirements_response CHECK (decision = 'PENDING' OR responded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_duplicate_case_events'), 'Create append-only duplicate-case lifecycle history.', <<<'SQL'
CREATE TABLE people_duplicate_case_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_account_id BIGINT UNSIGNED NULL,
    reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_duplicate_case_events_public_id (public_id),
    KEY ix_people_duplicate_case_events_case_occurred (case_id, occurred_at, id),
    CONSTRAINT fk_people_duplicate_case_events_case FOREIGN KEY (case_id) REFERENCES people_duplicate_cases (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_duplicate_case_events_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_duplicate_case_events_type CHECK (event_type IN ('REPORTED','CONSENT_REQUIREMENTS_CREATED','CONSENT_APPROVED','CONSENT_DECLINED','CONSENT_REQUIREMENTS_INVALIDATED','REVIEW_STARTED','DISMISSED','RESOLUTION_BLOCKED','RESOLVED')),
    CONSTRAINT ck_people_duplicate_case_events_actor CHECK (actor_type IN ('ACCOUNT','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_duplicate_case_event_update_trigger'), 'Reject mutation of duplicate-case history.', "CREATE TRIGGER tr_people_duplicate_case_events_no_update BEFORE UPDATE ON people_duplicate_case_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate case events are immutable'"),
            new SqlMigrationStep(new MigrationStepId('005_duplicate_case_event_delete_trigger'), 'Reject deletion of duplicate-case history.', "CREATE TRIGGER tr_people_duplicate_case_events_no_delete BEFORE DELETE ON people_duplicate_case_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate case events are immutable'"),
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
