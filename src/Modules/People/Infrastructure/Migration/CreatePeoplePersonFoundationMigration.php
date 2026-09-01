<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Migration;

use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyAdministrativeAreaHierarchyMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePeoplePersonFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901030100_create_people_person_foundation');
    }

    public function description(): string
    {
        return 'Create global Person, name-history and Account self-link foundations.';
    }

    public function dependencies(): array
    {
        return [(new CreateGeographyAdministrativeAreaHierarchyMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_people_persons'), 'Create global Person records.', <<<'SQL'
CREATE TABLE people_persons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    registry_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE',
    birth_date DATE NULL,
    sex_classification VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'NOT_RECORDED',
    nationality_country_id BIGINT UNSIGNED NULL,
    created_by_account_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    restricted_at DATETIME(6) NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_persons_public_id (public_id),
    UNIQUE KEY uq_people_persons_registry_code (registry_code),
    UNIQUE KEY uq_people_persons_id_status (id, status),
    KEY ix_people_persons_status_created (status, created_at, id),
    KEY ix_people_persons_creator_created (created_by_account_id, created_at, id),
    KEY ix_people_persons_nationality_status (nationality_country_id, status, id),
    CONSTRAINT fk_people_persons_nationality FOREIGN KEY (nationality_country_id)
        REFERENCES geography_countries (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_persons_creator FOREIGN KEY (created_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_persons_status CHECK (status IN ('ACTIVE','RESTRICTED','RETIRED')),
    CONSTRAINT ck_people_persons_sex CHECK (sex_classification IN ('MALE','FEMALE','NOT_RECORDED')),
    CONSTRAINT ck_people_persons_version CHECK (version >= 1),
    CONSTRAINT ck_people_persons_restricted CHECK (status <> 'RESTRICTED' OR restricted_at IS NOT NULL),
    CONSTRAINT ck_people_persons_retired CHECK (status <> 'RETIRED' OR retired_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_people_person_names'), 'Create versioned private Person names.', <<<'SQL'
CREATE TABLE people_person_names (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    name_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    script_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    given_name VARCHAR(200) NULL,
    middle_names VARCHAR(200) NULL,
    family_name VARCHAR(200) NULL,
    display_name VARCHAR(200) NOT NULL,
    search_name VARCHAR(256) NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    effective_at DATETIME(6) NOT NULL,
    superseded_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_name_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_person_names_public_id (public_id),
    UNIQUE KEY uq_people_person_names_active_type (person_id, name_type, active_name_marker),
    KEY ix_people_person_names_person_status (person_id, status, name_type, id),
    KEY ix_people_person_names_search_status (search_name, status, id),
    CONSTRAINT fk_people_person_names_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_person_names_type CHECK (name_type IN ('PRIMARY','PREFERRED','ARABIC','ALIAS')),
    CONSTRAINT ck_people_person_names_script CHECK (script_code IN ('LATIN','ARABIC','OTHER')),
    CONSTRAINT ck_people_person_names_status CHECK (status IN ('ACTIVE','SUPERSEDED')),
    CONSTRAINT ck_people_person_names_version CHECK (version >= 1),
    CONSTRAINT ck_people_person_names_superseded CHECK (status <> 'SUPERSEDED' OR superseded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_people_account_links'), 'Create historical Account-to-Person self links.', <<<'SQL'
CREATE TABLE people_account_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    link_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    linked_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_account_marker BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' AND link_type = 'SELF' THEN account_id ELSE NULL END) STORED,
    active_person_marker BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' AND link_type = 'SELF' THEN person_id ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_account_links_public_id (public_id),
    UNIQUE KEY uq_people_account_links_active_account (active_account_marker),
    UNIQUE KEY uq_people_account_links_active_person (active_person_marker),
    KEY ix_people_account_links_account_status (account_id, status, id),
    KEY ix_people_account_links_person_status (person_id, status, id),
    CONSTRAINT fk_people_account_links_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_account_links_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_account_links_type CHECK (link_type = 'SELF'),
    CONSTRAINT ck_people_account_links_status CHECK (status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_people_account_links_version CHECK (version >= 1),
    CONSTRAINT ck_people_account_links_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_people_account_links'), 'Drop Account-to-Person links.', 'DROP TABLE people_account_links'),
            new SqlMigrationStep(new MigrationStepId('002_drop_people_person_names'), 'Drop Person name history.', 'DROP TABLE people_person_names'),
            new SqlMigrationStep(new MigrationStepId('003_drop_people_persons'), 'Drop Person records.', 'DROP TABLE people_persons'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
