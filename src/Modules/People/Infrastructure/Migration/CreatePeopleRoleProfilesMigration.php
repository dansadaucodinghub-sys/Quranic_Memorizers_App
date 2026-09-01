<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePeopleRoleProfilesMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901030300_create_people_role_profiles');
    }

    public function description(): string
    {
        return 'Create Person participation roles and self-declared Memorizer progress.';
    }

    public function dependencies(): array
    {
        return [(new CreatePeopleGeographyAssociationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_people_role_profiles'), 'Create Person role profiles.', <<<'SQL'
CREATE TABLE people_role_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    role_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    activated_at DATETIME(6) NULL,
    deactivated_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_role_profiles_public_id (public_id),
    UNIQUE KEY uq_people_role_profiles_person_role (person_id, role_type),
    KEY ix_people_role_profiles_person_status (person_id, status, role_type, id),
    KEY ix_people_role_profiles_type_status (role_type, status, created_at, id),
    CONSTRAINT fk_people_role_profiles_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_role_profiles_type CHECK (role_type IN ('MEMORIZER','RECITER','COMPETITOR','GUARDIAN')),
    CONSTRAINT ck_people_role_profiles_status CHECK (status IN ('ACTIVE','INACTIVE')),
    CONSTRAINT ck_people_role_profiles_source CHECK (source_type IN ('SELF_DECLARED','GUARDIAN_DECLARED')),
    CONSTRAINT ck_people_role_profiles_version CHECK (version >= 1),
    CONSTRAINT ck_people_role_profiles_active CHECK (status <> 'ACTIVE' OR activated_at IS NOT NULL),
    CONSTRAINT ck_people_role_profiles_inactive CHECK (status <> 'INACTIVE' OR deactivated_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_people_memorizer_progress'), 'Create self-declared Memorizer progress.', <<<'SQL'
CREATE TABLE people_memorizer_progress (
    person_id BIGINT UNSIGNED NOT NULL,
    role_profile_id BIGINT UNSIGNED NOT NULL,
    progress_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    memorized_juz_count TINYINT UNSIGNED NOT NULL,
    completed_on DATE NULL,
    source_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (person_id),
    UNIQUE KEY uq_people_memorizer_progress_role (role_profile_id),
    CONSTRAINT fk_people_memorizer_progress_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_memorizer_progress_role FOREIGN KEY (role_profile_id)
        REFERENCES people_role_profiles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_memorizer_progress_status CHECK (progress_status IN ('NOT_RECORDED','IN_PROGRESS','COMPLETE','MAINTENANCE')),
    CONSTRAINT ck_people_memorizer_progress_juz CHECK (memorized_juz_count BETWEEN 0 AND 30),
    CONSTRAINT ck_people_memorizer_progress_source CHECK (source_type IN ('SELF_DECLARED','GUARDIAN_DECLARED')),
    CONSTRAINT ck_people_memorizer_progress_complete CHECK (
        progress_status NOT IN ('COMPLETE','MAINTENANCE') OR memorized_juz_count = 30
    ),
    CONSTRAINT ck_people_memorizer_progress_completed_on CHECK (
        completed_on IS NULL OR progress_status IN ('COMPLETE','MAINTENANCE')
    ),
    CONSTRAINT ck_people_memorizer_progress_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_people_memorizer_progress'), 'Drop Memorizer progress.', 'DROP TABLE people_memorizer_progress'),
            new SqlMigrationStep(new MigrationStepId('002_drop_people_role_profiles'), 'Drop Person role profiles.', 'DROP TABLE people_role_profiles'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
