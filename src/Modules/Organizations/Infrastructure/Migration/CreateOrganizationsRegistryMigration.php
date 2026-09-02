<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateOrganizationsRegistryMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901040200_create_organizations_registry');
    }
    public function description(): string
    {
        return 'Create workspace-scoped Organization, name, classification, and jurisdiction history.';
    }
    public function dependencies(): array
    {
        return [(new CreateOrganizationClassificationAndSecurityCatalogMigration())->id()];
    }
    public function up(): array
    {
        return [
        new SqlMigrationStep(new MigrationStepId('001_create_organizations'), 'Create tenant-owned Organization records.', <<<'SQL'
CREATE TABLE organizations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, registry_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE', created_by_account_id BIGINT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_organizations_public_id (public_id), UNIQUE KEY uq_organizations_registry_code (registry_code), UNIQUE KEY uq_organizations_workspace_id_id (workspace_id,id), KEY ix_organizations_workspace_status_created (workspace_id,status,created_at,id), KEY ix_organizations_creator_created (created_by_account_id,created_at,id),
 CONSTRAINT fk_organizations_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_organizations_creator FOREIGN KEY (created_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_organizations_status CHECK (status IN ('ACTIVE','RETIRED')), CONSTRAINT ck_organizations_version CHECK (version >= 1), CONSTRAINT ck_organizations_retired CHECK (status <> 'RETIRED' OR retired_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('002_create_organization_names'), 'Create private Organization name history.', <<<'SQL'
CREATE TABLE organization_names (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, name_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, script_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, display_name VARCHAR(240) NOT NULL, search_name VARCHAR(320) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, effective_at DATETIME(6) NOT NULL, superseded_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, active_name_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_names_public_id (public_id), UNIQUE KEY uq_organization_names_active_type (workspace_id,organization_id,name_type,active_name_marker), KEY ix_organization_names_workspace_search (workspace_id,search_name,status,id), KEY ix_organization_names_organization_status (workspace_id,organization_id,status,name_type,id), CONSTRAINT fk_organization_names_organization FOREIGN KEY (workspace_id,organization_id) REFERENCES organizations (workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_organization_names_type CHECK (name_type IN ('PRIMARY','SHORT','ARABIC','ALIAS')), CONSTRAINT ck_organization_names_script CHECK (script_code IN ('LATIN','ARABIC','OTHER')), CONSTRAINT ck_organization_names_status CHECK (status IN ('ACTIVE','SUPERSEDED')), CONSTRAINT ck_organization_names_version CHECK (version >= 1), CONSTRAINT ck_organization_names_superseded CHECK (status <> 'SUPERSEDED' OR superseded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('003_create_organization_classification_assignments'), 'Create historical Organization classifications.', <<<'SQL'
CREATE TABLE organization_classification_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, classification_id BIGINT UNSIGNED NOT NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, effective_at DATETIME(6) NOT NULL, removed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, active_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED, active_primary_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' AND is_primary=1 THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_classification_assignments_public_id (public_id), UNIQUE KEY uq_organization_classification_active (workspace_id,organization_id,classification_id,active_marker), UNIQUE KEY uq_organization_classification_primary (workspace_id,organization_id,active_primary_marker), KEY ix_organization_classification_organization (workspace_id,organization_id,status,id), CONSTRAINT fk_organization_classification_organization FOREIGN KEY (workspace_id,organization_id) REFERENCES organizations (workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_organization_classification_classification FOREIGN KEY (classification_id) REFERENCES organization_classifications (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_organization_classification_status CHECK (status IN ('ACTIVE','REMOVED')), CONSTRAINT ck_organization_classification_version CHECK (version >= 1), CONSTRAINT ck_organization_classification_removed CHECK (status <> 'REMOVED' OR removed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('004_create_organization_jurisdictions'), 'Create self-declared Organization jurisdiction history.', <<<'SQL'
CREATE TABLE organization_jurisdictions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, jurisdiction_level VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, country_id BIGINT UNSIGNED NULL, level_one_area_id BIGINT UNSIGNED NULL, level_two_area_id BIGINT UNSIGNED NULL, source_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, effective_at DATETIME(6) NOT NULL, superseded_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, active_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_jurisdictions_public_id (public_id), UNIQUE KEY uq_organization_jurisdiction_active (workspace_id,organization_id,active_marker), KEY ix_organization_jurisdictions_organization (workspace_id,organization_id,status,id), CONSTRAINT fk_organization_jurisdiction_organization FOREIGN KEY (workspace_id,organization_id) REFERENCES organizations (workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_organization_jurisdiction_country FOREIGN KEY (country_id) REFERENCES geography_countries (id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_organization_jurisdiction_level_one FOREIGN KEY (country_id,level_one_area_id) REFERENCES geography_administrative_areas (country_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_organization_jurisdiction_level_two FOREIGN KEY (country_id,level_one_area_id,level_two_area_id) REFERENCES geography_administrative_areas (country_id,parent_area_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_organization_jurisdiction_level CHECK (jurisdiction_level IN ('NOT_RECORDED','COUNTRY','LEVEL_1','LEVEL_2')), CONSTRAINT ck_organization_jurisdiction_source CHECK (source_type='SELF_DECLARED'), CONSTRAINT ck_organization_jurisdiction_status CHECK (status IN ('ACTIVE','SUPERSEDED')), CONSTRAINT ck_organization_jurisdiction_shape CHECK ((jurisdiction_level='NOT_RECORDED' AND country_id IS NULL AND level_one_area_id IS NULL AND level_two_area_id IS NULL) OR (jurisdiction_level='COUNTRY' AND country_id IS NOT NULL AND level_one_area_id IS NULL AND level_two_area_id IS NULL) OR (jurisdiction_level='LEVEL_1' AND country_id IS NOT NULL AND level_one_area_id IS NOT NULL AND level_two_area_id IS NULL) OR (jurisdiction_level='LEVEL_2' AND country_id IS NOT NULL AND level_one_area_id IS NOT NULL AND level_two_area_id IS NOT NULL)), CONSTRAINT ck_organization_jurisdiction_version CHECK (version >= 1), CONSTRAINT ck_organization_jurisdiction_superseded CHECK (status <> 'SUPERSEDED' OR superseded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }
    public function down(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_drop_organization_jurisdictions'), 'Drop Organization jurisdiction history.', 'DROP TABLE organization_jurisdictions'),new SqlMigrationStep(new MigrationStepId('002_drop_organization_classification_assignments'), 'Drop Organization classification history.', 'DROP TABLE organization_classification_assignments'),new SqlMigrationStep(new MigrationStepId('003_drop_organization_names'), 'Drop Organization name history.', 'DROP TABLE organization_names'),new SqlMigrationStep(new MigrationStepId('004_drop_organizations'), 'Drop Organizations.', 'DROP TABLE organizations')];
    }
    public function reversible(): bool
    {
        return true;
    }
}
