<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateGeographyCountryAndDatasetFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260831000100_create_geography_country_and_dataset_foundation');
    }

    public function description(): string
    {
        return 'Create global geography country and dataset-version reference foundation.';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_geography_countries'),
                'Create immutable global geography country records.',
                <<<'SQL'
CREATE TABLE geography_countries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    iso_alpha2 CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    iso_alpha3 CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    iso_numeric CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    common_name VARCHAR(191) NOT NULL,
    official_name VARCHAR(191) NOT NULL,
    canonical_slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geography_countries_public_id (public_id),
    UNIQUE KEY uq_geography_countries_alpha2 (iso_alpha2),
    UNIQUE KEY uq_geography_countries_alpha3 (iso_alpha3),
    UNIQUE KEY uq_geography_countries_numeric (iso_numeric),
    UNIQUE KEY uq_geography_countries_slug (canonical_slug),
    UNIQUE KEY uq_geography_countries_id_status (id, status),
    KEY ix_geography_countries_status_name_id (status, common_name, id),
    CONSTRAINT ck_geography_countries_status CHECK (status IN ('ACTIVE', 'RETIRED')),
    CONSTRAINT ck_geography_countries_version CHECK (version >= 1),
    CONSTRAINT ck_geography_countries_retired CHECK (
        (status = 'ACTIVE' AND retired_at IS NULL) OR (status = 'RETIRED' AND retired_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_geography_dataset_versions'),
                'Create checksum-governed geography dataset version records.',
                <<<'SQL'
CREATE TABLE geography_dataset_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    country_id BIGINT UNSIGNED NOT NULL,
    dataset_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    dataset_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_authority VARCHAR(191) NOT NULL,
    source_title VARCHAR(255) NOT NULL,
    source_reference VARCHAR(512) NOT NULL,
    source_published_on DATE NULL,
    source_retrieved_at DATETIME(6) NOT NULL,
    content_sha256 BINARY(32) NOT NULL,
    record_count INT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    imported_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    superseded_at DATETIME(6) NULL,
    active_country_marker BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(status = 'ACTIVE', country_id, NULL)
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geography_dataset_versions_public_id (public_id),
    UNIQUE KEY uq_geography_dataset_versions_code_version (dataset_code, dataset_version),
    UNIQUE KEY uq_geography_dataset_versions_active_country (active_country_marker),
    UNIQUE KEY uq_geography_dataset_versions_id_country (id, country_id),
    KEY ix_geography_dataset_versions_country_status_imported (country_id, status, imported_at, id),
    KEY ix_geography_dataset_versions_status_updated (status, updated_at, id),
    CONSTRAINT fk_geography_dataset_versions_country FOREIGN KEY (country_id)
        REFERENCES geography_countries (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_geography_dataset_versions_status CHECK (status IN ('ACTIVE', 'SUPERSEDED')),
    CONSTRAINT ck_geography_dataset_versions_records CHECK (record_count >= 1),
    CONSTRAINT ck_geography_dataset_versions_version CHECK (version >= 1),
    CONSTRAINT ck_geography_dataset_versions_superseded CHECK (
        (status = 'ACTIVE' AND superseded_at IS NULL) OR (status = 'SUPERSEDED' AND superseded_at IS NOT NULL)
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
                new MigrationStepId('001_drop_geography_dataset_versions'),
                'Drop geography dataset version records in local or test environments.',
                'DROP TABLE geography_dataset_versions',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_geography_countries'),
                'Drop geography country records in local or test environments.',
                'DROP TABLE geography_countries',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
