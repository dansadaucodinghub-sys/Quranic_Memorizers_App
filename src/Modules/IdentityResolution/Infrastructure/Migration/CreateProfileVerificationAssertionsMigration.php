<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateProfileVerificationAssertionsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902050200_create_profile_verification_assertions');
    }

    public function description(): string
    {
        return 'Create private QMDB profile record-status assertions.';
    }

    public function dependencies(): array
    {
        return [(new CreateProfileClaimPairingAndClaimMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_create_profile_verification_assertions'), 'Create active and historical QMDB record-status assertions.', <<<'SQL'
CREATE TABLE people_profile_verification_assertions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    assertion_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authority_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_account_id BIGINT UNSIGNED NOT NULL,
    claim_id BIGINT UNSIGNED NULL,
    guardianship_id BIGINT UNSIGNED NULL,
    reference_code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    review_justification VARCHAR(2000) NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    recorded_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_assertion_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_profile_verification_assertions_public_id (public_id),
    UNIQUE KEY uq_people_profile_verification_assertions_active_type (person_id, assertion_type, active_assertion_marker),
    KEY ix_people_profile_verification_assertions_person_status (person_id, status, assertion_type, id),
    KEY ix_people_profile_verification_assertions_actor_recorded (actor_account_id, recorded_at, id),
    CONSTRAINT fk_people_profile_verification_assertions_person FOREIGN KEY (person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_verification_assertions_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_verification_assertions_claim FOREIGN KEY (claim_id) REFERENCES people_profile_claims (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_verification_assertions_guardianship FOREIGN KEY (guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_profile_verification_assertions_type CHECK (assertion_type IN ('ACCOUNT_CLAIMED','GUARDIAN_CONFIRMED','QMDB_RECORD_REVIEWED')),
    CONSTRAINT ck_people_profile_verification_assertions_authority CHECK (authority_type IN ('ACCOUNT','GUARDIAN','PLATFORM_REVIEWER')),
    CONSTRAINT ck_people_profile_verification_assertions_status CHECK (status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_people_profile_verification_assertions_version CHECK (version >= 1),
    CONSTRAINT ck_people_profile_verification_assertions_recorded CHECK (status <> 'ACTIVE' OR recorded_at IS NOT NULL),
    CONSTRAINT ck_people_profile_verification_assertions_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL)];
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
