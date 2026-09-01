<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePeopleGeographyAssociationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901030200_create_people_geography_associations');
    }

    public function description(): string
    {
        return 'Create versioned Person origin and residence geography associations.';
    }

    public function dependencies(): array
    {
        return [(new CreatePeoplePersonFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_add_geography_parent_candidate_key'),
                'Add the Geography parent candidate key for Person associations.',
                'ALTER TABLE geography_administrative_areas ADD UNIQUE KEY uq_geography_administrative_areas_country_parent_id (country_id, parent_area_id, id)'
            ),
            new SqlMigrationStep(new MigrationStepId('002_create_people_person_geographies'), 'Create Person geography association history.', <<<'SQL'
CREATE TABLE people_person_geographies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    association_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_id BIGINT UNSIGNED NOT NULL,
    level_one_area_id BIGINT UNSIGNED NULL,
    level_two_area_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    effective_at DATETIME(6) NOT NULL,
    superseded_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_association_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_person_geographies_public_id (public_id),
    UNIQUE KEY uq_people_person_geographies_active_type (person_id, association_type, active_association_marker),
    KEY ix_people_person_geographies_person_status (person_id, status, association_type, id),
    KEY ix_people_person_geographies_hierarchy (country_id, level_one_area_id, level_two_area_id, status),
    CONSTRAINT fk_people_person_geographies_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_geographies_country FOREIGN KEY (country_id)
        REFERENCES geography_countries (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_geographies_level_one FOREIGN KEY (country_id, level_one_area_id)
        REFERENCES geography_administrative_areas (country_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_geographies_level_two FOREIGN KEY (country_id, level_one_area_id, level_two_area_id)
        REFERENCES geography_administrative_areas (country_id, parent_area_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_person_geographies_type CHECK (association_type IN ('ORIGIN','RESIDENCE')),
    CONSTRAINT ck_people_person_geographies_source CHECK (source_type IN ('SELF_DECLARED','GUARDIAN_DECLARED')),
    CONSTRAINT ck_people_person_geographies_status CHECK (status IN ('ACTIVE','SUPERSEDED')),
    CONSTRAINT ck_people_person_geographies_level_two CHECK (level_two_area_id IS NULL OR level_one_area_id IS NOT NULL),
    CONSTRAINT ck_people_person_geographies_version CHECK (version >= 1),
    CONSTRAINT ck_people_person_geographies_superseded CHECK (status <> 'SUPERSEDED' OR superseded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_people_person_geographies'), 'Drop Person geography association history.', 'DROP TABLE people_person_geographies'),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_geography_parent_candidate_key'),
                'Remove Person Geography candidate key.',
                'ALTER TABLE geography_administrative_areas DROP INDEX uq_geography_administrative_areas_country_parent_id'
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
