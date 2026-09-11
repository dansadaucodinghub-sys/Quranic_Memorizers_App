<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Infrastructure\Migration;

use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration\ExtendQuranPublicSearchRateLimitMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * P5 configuration is workspace-owned.  Composite foreign keys make a row from
 * one workspace impossible to attach to a parent belonging to another one.
 */
final readonly class CreateCompetitionConfigurationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911130000_create_competition_configuration');
    }
    public function description(): string
    {
        return 'Create workspace-owned competition program, edition, category, and immutable configuration records.';
    }
    public function dependencies(): array
    {
        return [(new ExtendQuranPublicSearchRateLimitMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_programs'), 'Create recurring workspace competition programs.', <<<'SQL'
CREATE TABLE competition_programs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, primary_organizer_organization_id BIGINT UNSIGNED NOT NULL,
 program_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, slug VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, name_en VARCHAR(200) NOT NULL, name_ar VARCHAR(200) NULL, description_en TEXT NULL, description_ar TEXT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT', version INT UNSIGNED NOT NULL DEFAULT 1, created_by_account_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_competition_programs_public (public_id), UNIQUE KEY uq_competition_programs_code (workspace_id,program_code), UNIQUE KEY uq_competition_programs_slug (workspace_id,slug), UNIQUE KEY uq_competition_programs_workspace_id (workspace_id,id), KEY ix_competition_programs_status (workspace_id,status,updated_at,id), KEY ix_competition_programs_organizer (workspace_id,primary_organizer_organization_id,status,id),
 CONSTRAINT fk_competition_programs_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_programs_organizer FOREIGN KEY (workspace_id,primary_organizer_organization_id) REFERENCES organizations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_programs_creator FOREIGN KEY (created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_competition_programs_status CHECK (status IN ('DRAFT','ACTIVE','RETIRED')), CONSTRAINT ck_competition_programs_version CHECK (version >= 1), CONSTRAINT ck_competition_programs_activation CHECK ((status <> 'ACTIVE' OR activated_at IS NOT NULL) AND (status <> 'RETIRED' OR retired_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_program_organizers'), 'Create primary, co-organizer, and host assignments.', <<<'SQL'
CREATE TABLE competition_program_organizers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, program_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL,
 organizer_role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, primary_role_marker TINYINT GENERATED ALWAYS AS (CASE WHEN organizer_role='PRIMARY' THEN 1 ELSE NULL END) STORED, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_program_organizers_public(public_id), UNIQUE KEY uq_competition_program_organizer(workspace_id,program_id,organization_id), UNIQUE KEY uq_competition_program_primary(workspace_id,program_id,primary_role_marker),
 CONSTRAINT fk_competition_program_organizers_program FOREIGN KEY(workspace_id,program_id) REFERENCES competition_programs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_program_organizers_organization FOREIGN KEY(workspace_id,organization_id) REFERENCES organizations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_program_organizer_role CHECK(organizer_role IN ('PRIMARY','CO_ORGANIZER','HOST'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_editions'), 'Create time-bound competition editions.', <<<'SQL'
CREATE TABLE competition_editions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, program_id BIGINT UNSIGNED NOT NULL, edition_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, slug VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 title_en VARCHAR(200) NOT NULL, title_ar VARCHAR(200) NULL, summary_en TEXT NULL, summary_ar TEXT NULL, scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, country_id BIGINT UNSIGNED NULL, state_id BIGINT UNSIGNED NULL, lga_id BIGINT UNSIGNED NULL, scope_organization_id BIGINT UNSIGNED NULL, other_scope_label_en VARCHAR(200) NULL, other_scope_label_ar VARCHAR(200) NULL,
 timezone_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, eligibility_reference_date DATE NOT NULL, competition_starts_at DATETIME(6) NOT NULL, competition_ends_at DATETIME(6) NOT NULL, status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT', public_visibility VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PRIVATE', current_snapshot_id BIGINT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_by_account_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, published_at DATETIME(6) NULL, registration_opened_at DATETIME(6) NULL, registration_closed_at DATETIME(6) NULL, roster_finalized_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_editions_public(public_id), UNIQUE KEY uq_competition_editions_code(workspace_id,edition_code), UNIQUE KEY uq_competition_editions_slug(workspace_id,slug), UNIQUE KEY uq_competition_editions_workspace_id(workspace_id,id), KEY ix_competition_editions_workspace_status(workspace_id,status,competition_starts_at,id), KEY ix_competition_editions_scope(workspace_id,scope_type,state_id,lga_id,status,id), KEY ix_competition_editions_public(public_visibility,status,competition_starts_at,id),
 CONSTRAINT fk_competition_editions_program FOREIGN KEY(workspace_id,program_id) REFERENCES competition_programs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_country FOREIGN KEY(country_id) REFERENCES geography_countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_state FOREIGN KEY(country_id,state_id) REFERENCES geography_administrative_areas(country_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_lga FOREIGN KEY(country_id,state_id,lga_id) REFERENCES geography_administrative_areas(country_id,parent_area_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_organization FOREIGN KEY(workspace_id,scope_organization_id) REFERENCES organizations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_editions_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_competition_editions_scope CHECK(scope_type IN ('NATIONAL','STATE','LGA','ORGANIZATION','SCHOOL','OTHER')), CONSTRAINT ck_competition_editions_status CHECK(status IN ('DRAFT','PUBLISHED','REGISTRATION_OPEN','REGISTRATION_CLOSED','ROSTER_FINALIZED','CANCELLED')), CONSTRAINT ck_competition_editions_visibility CHECK(public_visibility IN ('PRIVATE','PUBLIC')), CONSTRAINT ck_competition_editions_dates CHECK(competition_ends_at>competition_starts_at), CONSTRAINT ck_competition_editions_version CHECK(version>=1), CONSTRAINT ck_competition_editions_scope_shape CHECK((scope_type='NATIONAL' AND country_id IS NOT NULL AND state_id IS NULL AND lga_id IS NULL) OR (scope_type='STATE' AND country_id IS NOT NULL AND state_id IS NOT NULL AND lga_id IS NULL) OR (scope_type='LGA' AND country_id IS NOT NULL AND state_id IS NOT NULL AND lga_id IS NOT NULL) OR (scope_type='ORGANIZATION' AND scope_organization_id IS NOT NULL) OR (scope_type='SCHOOL' AND scope_organization_id IS NOT NULL) OR (scope_type='OTHER' AND (other_scope_label_en IS NOT NULL OR other_scope_label_ar IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_edition_organizers'), 'Create edition organizer assignments.', <<<'SQL'
CREATE TABLE competition_edition_organizers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, organizer_role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0, primary_role_marker TINYINT GENERATED ALWAYS AS(CASE WHEN organizer_role='PRIMARY' THEN 1 ELSE NULL END) STORED, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_edition_organizers_public(public_id), UNIQUE KEY uq_competition_edition_organizer(workspace_id,edition_id,organization_id), UNIQUE KEY uq_competition_edition_primary(workspace_id,edition_id,primary_role_marker), CONSTRAINT fk_competition_edition_organizers_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_edition_organizers_organization FOREIGN KEY(workspace_id,organization_id) REFERENCES organizations(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_edition_organizer_role CHECK(organizer_role IN ('PRIMARY','CO_ORGANIZER','HOST'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_venues'), 'Create encrypted physical, online, and hybrid venue configurations.', <<<'SQL'
CREATE TABLE competition_venues (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, venue_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, venue_name VARCHAR(200) NOT NULL, country_id BIGINT UNSIGNED NULL, state_id BIGINT UNSIGNED NULL, lga_id BIGINT UNSIGNED NULL, address_line_1 VARCHAR(255) NULL, address_line_2 VARCHAR(255) NULL, city_or_locality VARCHAR(160) NULL, public_information_en TEXT NULL, public_information_ar TEXT NULL, private_access_ciphertext MEDIUMBLOB NULL, private_access_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_competition_venues_public(public_id), UNIQUE KEY uq_competition_venues_workspace_id(workspace_id,id), KEY ix_competition_venues_edition(workspace_id,edition_id,id), CONSTRAINT fk_competition_venues_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_venues_country FOREIGN KEY(country_id) REFERENCES geography_countries(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_venues_state FOREIGN KEY(country_id,state_id) REFERENCES geography_administrative_areas(country_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_competition_venues_lga FOREIGN KEY(country_id,state_id,lga_id) REFERENCES geography_administrative_areas(country_id,parent_area_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_competition_venues_type CHECK(venue_type IN ('PHYSICAL','ONLINE','HYBRID')), CONSTRAINT ck_competition_venues_version CHECK(version>=1), CONSTRAINT ck_competition_venues_private_access CHECK((private_access_ciphertext IS NULL AND private_access_key_id IS NULL) OR (private_access_ciphertext IS NOT NULL AND private_access_key_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
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
