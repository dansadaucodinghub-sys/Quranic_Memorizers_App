<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateGeographyAdministrativeAreaHierarchyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260831000200_create_geography_administrative_area_hierarchy');
    }

    public function description(): string
    {
        return 'Create global typed Nigerian administrative-area hierarchy.';
    }

    public function dependencies(): array
    {
        return [(new CreateGeographyCountryAndDatasetFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_geography_administrative_areas'),
                'Create country-safe typed administrative-area reference records.',
                <<<'SQL'
CREATE TABLE geography_administrative_areas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    country_id BIGINT UNSIGNED NOT NULL,
    dataset_version_id BIGINT UNSIGNED NOT NULL,
    parent_area_id BIGINT UNSIGNED NULL,
    administrative_level TINYINT UNSIGNED NOT NULL,
    area_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    canonical_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    official_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    canonical_slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    official_name VARCHAR(191) NOT NULL,
    search_name VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    parent_scope_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(parent_area_id, 0)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geography_administrative_areas_public_id (public_id),
    UNIQUE KEY uq_geography_administrative_areas_code (canonical_code),
    UNIQUE KEY uq_geography_administrative_areas_parent_slug (country_id, parent_scope_id, canonical_slug),
    UNIQUE KEY uq_geography_administrative_areas_country_id (country_id, id),
    KEY ix_geography_administrative_areas_country_level_name (country_id, administrative_level, status, official_name, id),
    KEY ix_geography_administrative_areas_parent_name (parent_area_id, status, official_name, id),
    KEY ix_geography_administrative_areas_country_type (country_id, area_type, status, id),
    KEY ix_geography_administrative_areas_country_search (country_id, search_name, status, id),
    KEY ix_geography_administrative_areas_dataset_status (dataset_version_id, status, id),
    CONSTRAINT fk_geography_administrative_areas_dataset FOREIGN KEY (dataset_version_id, country_id)
        REFERENCES geography_dataset_versions (id, country_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_geography_administrative_areas_parent FOREIGN KEY (country_id, parent_area_id)
        REFERENCES geography_administrative_areas (country_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_geography_administrative_areas_level CHECK (administrative_level IN (1, 2)),
    CONSTRAINT ck_geography_administrative_areas_type CHECK (area_type IN (
        'STATE', 'FEDERAL_CAPITAL_TERRITORY', 'LOCAL_GOVERNMENT_AREA', 'AREA_COUNCIL'
    )),
    CONSTRAINT ck_geography_administrative_areas_parent CHECK (
        (administrative_level = 1 AND parent_area_id IS NULL) OR
        (administrative_level = 2 AND parent_area_id IS NOT NULL)
    ),
    CONSTRAINT ck_geography_administrative_areas_type_level CHECK (
        (area_type IN ('STATE', 'FEDERAL_CAPITAL_TERRITORY') AND administrative_level = 1) OR
        (area_type IN ('LOCAL_GOVERNMENT_AREA', 'AREA_COUNCIL') AND administrative_level = 2)
    ),
    CONSTRAINT ck_geography_administrative_areas_status CHECK (status IN ('ACTIVE', 'RETIRED')),
    CONSTRAINT ck_geography_administrative_areas_version CHECK (version >= 1),
    CONSTRAINT ck_geography_administrative_areas_retired CHECK (
        (status = 'ACTIVE' AND retired_at IS NULL) OR (status = 'RETIRED' AND retired_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_geography_administrative_areas'),
                'Drop geography administrative areas in local or test environments.',
                'DROP TABLE geography_administrative_areas',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
