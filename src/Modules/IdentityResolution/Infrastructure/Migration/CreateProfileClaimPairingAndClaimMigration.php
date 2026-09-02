<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Migration;

use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\ExtendOrganizationAffiliationExpiryNotificationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateProfileClaimPairingAndClaimMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902050100_create_profile_claim_pairing_and_claim');
    }

    public function description(): string
    {
        return 'Create private profile claim pairings, claims, and immutable lifecycle history.';
    }

    public function dependencies(): array
    {
        return [(new ExtendOrganizationAffiliationExpiryNotificationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_profile_claim_pairings'), 'Create hash-only Account-to-Person claim pairing records.', <<<'SQL'
CREATE TABLE people_profile_claim_pairings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    code_selector CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    secret_hash BINARY(32) NOT NULL,
    hmac_key_version SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    exhausted_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_account_marker BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN account_id ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_profile_claim_pairings_public_id (public_id),
    UNIQUE KEY uq_people_profile_claim_pairings_selector (code_selector),
    UNIQUE KEY uq_people_profile_claim_pairings_active_account (active_account_marker),
    KEY ix_people_profile_claim_pairings_account_status (account_id, status, created_at, id),
    KEY ix_people_profile_claim_pairings_status_expires (status, expires_at, id),
    CONSTRAINT fk_people_profile_claim_pairings_account FOREIGN KEY (account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_profile_claim_pairings_status CHECK (status IN ('ACTIVE','CONSUMED','REVOKED','EXPIRED','ATTEMPTS_EXHAUSTED')),
    CONSTRAINT ck_people_profile_claim_pairings_attempt_count CHECK (attempt_count >= 0 AND attempt_count <= max_attempts),
    CONSTRAINT ck_people_profile_claim_pairings_max_attempts CHECK (max_attempts >= 1),
    CONSTRAINT ck_people_profile_claim_pairings_hmac_version CHECK (hmac_key_version >= 1),
    CONSTRAINT ck_people_profile_claim_pairings_version CHECK (version >= 1),
    CONSTRAINT ck_people_profile_claim_pairings_expiry CHECK (expires_at > created_at),
    CONSTRAINT ck_people_profile_claim_pairings_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL),
    CONSTRAINT ck_people_profile_claim_pairings_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_people_profile_claim_pairings_exhausted CHECK (status <> 'ATTEMPTS_EXHAUSTED' OR exhausted_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_profile_claims'), 'Create pending and historical profile claims.', <<<'SQL'
CREATE TABLE people_profile_claims (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    pairing_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    claimant_account_id BIGINT UNSIGNED NOT NULL,
    authorization_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authorized_by_account_id BIGINT UNSIGNED NOT NULL,
    authorization_guardianship_id BIGINT UNSIGNED NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    review_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    review_justification VARCHAR(2000) NULL,
    requested_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    accepted_at DATETIME(6) NULL,
    declined_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    expired_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    open_person_marker BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'PENDING_ACCEPTANCE' THEN person_id ELSE NULL END) STORED,
    open_account_marker BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'PENDING_ACCEPTANCE' THEN claimant_account_id ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_profile_claims_public_id (public_id),
    UNIQUE KEY uq_people_profile_claims_pairing (pairing_id),
    UNIQUE KEY uq_people_profile_claims_open_person (open_person_marker),
    UNIQUE KEY uq_people_profile_claims_open_account (open_account_marker),
    KEY ix_people_profile_claims_person_status (person_id, status, created_at, id),
    KEY ix_people_profile_claims_account_status (claimant_account_id, status, created_at, id),
    KEY ix_people_profile_claims_authorizer_status (authorized_by_account_id, status, created_at, id),
    KEY ix_people_profile_claims_status_expires (status, expires_at, id),
    CONSTRAINT fk_people_profile_claims_pairing FOREIGN KEY (pairing_id) REFERENCES people_profile_claim_pairings (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_claims_person FOREIGN KEY (person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_claims_claimant FOREIGN KEY (claimant_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_claims_authorizer FOREIGN KEY (authorized_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_claims_guardianship FOREIGN KEY (authorization_guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_profile_claims_authorization_type CHECK (authorization_type IN ('GUARDIAN','PLATFORM_RECORD_REVIEW')),
    CONSTRAINT ck_people_profile_claims_status CHECK (status IN ('PENDING_ACCEPTANCE','ACCEPTED','DECLINED','REVOKED','EXPIRED')),
    CONSTRAINT ck_people_profile_claims_version CHECK (version >= 1),
    CONSTRAINT ck_people_profile_claims_expiry CHECK (expires_at > requested_at),
    CONSTRAINT ck_people_profile_claims_guardian CHECK (authorization_type <> 'GUARDIAN' OR authorization_guardianship_id IS NOT NULL),
    CONSTRAINT ck_people_profile_claims_platform_review CHECK (authorization_type <> 'PLATFORM_RECORD_REVIEW' OR (authorization_guardianship_id IS NULL AND review_reference IS NOT NULL AND review_justification IS NOT NULL)),
    CONSTRAINT ck_people_profile_claims_accepted CHECK (status <> 'ACCEPTED' OR accepted_at IS NOT NULL),
    CONSTRAINT ck_people_profile_claims_declined CHECK (status <> 'DECLINED' OR declined_at IS NOT NULL),
    CONSTRAINT ck_people_profile_claims_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_people_profile_claims_expired CHECK (status <> 'EXPIRED' OR expired_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_profile_claim_events'), 'Create append-only profile claim lifecycle history.', <<<'SQL'
CREATE TABLE people_profile_claim_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    claim_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_account_id BIGINT UNSIGNED NULL,
    reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_profile_claim_events_public_id (public_id),
    KEY ix_people_profile_claim_events_claim_occurred (claim_id, occurred_at, id),
    CONSTRAINT fk_people_profile_claim_events_claim FOREIGN KEY (claim_id) REFERENCES people_profile_claims (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_claim_events_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_profile_claim_events_type CHECK (event_type IN ('AUTHORIZED','ACCEPTED','DECLINED','REVOKED','EXPIRED')),
    CONSTRAINT ck_people_profile_claim_events_actor CHECK (actor_type IN ('ACCOUNT','SYSTEM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_profile_claim_event_update_trigger'), 'Reject mutation of claim lifecycle history.', "CREATE TRIGGER tr_people_profile_claim_events_no_update BEFORE UPDATE ON people_profile_claim_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Profile claim events are immutable'"),
            new SqlMigrationStep(new MigrationStepId('005_profile_claim_event_delete_trigger'), 'Reject deletion of claim lifecycle history.', "CREATE TRIGGER tr_people_profile_claim_events_no_delete BEFORE DELETE ON people_profile_claim_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Profile claim events are immutable'"),
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
