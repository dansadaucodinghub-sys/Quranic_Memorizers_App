<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateOrganizationAffiliationAssignmentsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901040600_create_organization_affiliation_assignments');
    }
    public function description(): string
    {
        return 'Create tenant-scoped Organization affiliation unit and role assignment history.';
    }
    public function dependencies(): array
    {
        return [(new CreateOrganizationAffiliationsMigration())->id()];
    }
    public function up(): array
    {
        return [
        new SqlMigrationStep(new MigrationStepId('001_create_unit_assignments'), 'Create affiliation unit assignment history.', <<<'SQL'
CREATE TABLE organization_affiliation_unit_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, affiliation_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
 is_primary TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, assigned_by_account_id BIGINT UNSIGNED NOT NULL, removed_by_account_id BIGINT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1, proposed_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, removed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 open_assignment_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('PROPOSED','ACTIVE') THEN 1 ELSE NULL END) STORED,
 open_primary_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('PROPOSED','ACTIVE') AND is_primary=1 THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_affiliation_unit_assignments_public_id (public_id), UNIQUE KEY uq_organization_affiliation_unit_assignment_open (workspace_id,organization_id,affiliation_id,unit_id,open_assignment_marker), UNIQUE KEY uq_organization_affiliation_unit_assignment_primary (workspace_id,organization_id,affiliation_id,open_primary_marker), UNIQUE KEY uq_organization_affiliation_unit_assignment_scope (workspace_id,organization_id,affiliation_id,id),
 KEY ix_organization_affiliation_unit_assignments_affiliation (workspace_id,organization_id,affiliation_id,status,id), KEY ix_organization_affiliation_unit_assignments_unit (workspace_id,organization_id,unit_id,status,id),
 CONSTRAINT fk_organization_affiliation_unit_assignment_affiliation FOREIGN KEY (workspace_id,organization_id,affiliation_id) REFERENCES organization_affiliations (workspace_id,organization_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_unit_assignment_unit FOREIGN KEY (workspace_id,organization_id,unit_id) REFERENCES organization_units (workspace_id,organization_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_unit_assignment_assigned_by FOREIGN KEY (assigned_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_unit_assignment_removed_by FOREIGN KEY (removed_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_organization_affiliation_unit_assignment_primary CHECK (is_primary IN (0,1)), CONSTRAINT ck_organization_affiliation_unit_assignment_status CHECK (status IN ('PROPOSED','ACTIVE','REMOVED','CANCELLED')), CONSTRAINT ck_organization_affiliation_unit_assignment_version CHECK (version >= 1), CONSTRAINT ck_organization_affiliation_unit_assignment_proposed CHECK (status <> 'PROPOSED' OR proposed_at IS NOT NULL), CONSTRAINT ck_organization_affiliation_unit_assignment_active CHECK (status <> 'ACTIVE' OR activated_at IS NOT NULL), CONSTRAINT ck_organization_affiliation_unit_assignment_removed CHECK (status <> 'REMOVED' OR (removed_at IS NOT NULL AND removed_by_account_id IS NOT NULL)), CONSTRAINT ck_organization_affiliation_unit_assignment_cancelled CHECK (status <> 'CANCELLED' OR removed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('002_create_role_assignments'), 'Create affiliation role assignment history.', <<<'SQL'
CREATE TABLE organization_affiliation_role_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, affiliation_id BIGINT UNSIGNED NOT NULL, role_definition_id BIGINT UNSIGNED NOT NULL, unit_assignment_id BIGINT UNSIGNED NULL, display_title VARCHAR(160) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, assigned_by_account_id BIGINT UNSIGNED NOT NULL, removed_by_account_id BIGINT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1, proposed_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, removed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 unit_scope_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(unit_assignment_id,0)) STORED,
 open_assignment_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('PROPOSED','ACTIVE') THEN 1 ELSE NULL END) STORED,
 open_primary_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('PROPOSED','ACTIVE') AND is_primary=1 THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_affiliation_role_assignments_public_id (public_id), UNIQUE KEY uq_organization_affiliation_role_assignment_open (workspace_id,organization_id,affiliation_id,role_definition_id,unit_scope_id,open_assignment_marker), UNIQUE KEY uq_organization_affiliation_role_assignment_primary (workspace_id,organization_id,affiliation_id,open_primary_marker),
 KEY ix_organization_affiliation_role_assignments_affiliation (workspace_id,organization_id,affiliation_id,status,id), KEY ix_organization_affiliation_role_assignments_role (role_definition_id,status,id), KEY ix_organization_affiliation_role_assignments_unit (workspace_id,organization_id,unit_assignment_id,status,id),
 CONSTRAINT fk_organization_affiliation_role_assignment_affiliation FOREIGN KEY (workspace_id,organization_id,affiliation_id) REFERENCES organization_affiliations (workspace_id,organization_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_role_assignment_definition FOREIGN KEY (role_definition_id) REFERENCES organization_affiliation_role_definitions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_role_assignment_unit FOREIGN KEY (workspace_id,organization_id,affiliation_id,unit_assignment_id) REFERENCES organization_affiliation_unit_assignments (workspace_id,organization_id,affiliation_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_role_assignment_assigned_by FOREIGN KEY (assigned_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_role_assignment_removed_by FOREIGN KEY (removed_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_organization_affiliation_role_assignment_primary CHECK (is_primary IN (0,1)), CONSTRAINT ck_organization_affiliation_role_assignment_status CHECK (status IN ('PROPOSED','ACTIVE','REMOVED','CANCELLED')), CONSTRAINT ck_organization_affiliation_role_assignment_version CHECK (version >= 1), CONSTRAINT ck_organization_affiliation_role_assignment_proposed CHECK (status <> 'PROPOSED' OR proposed_at IS NOT NULL), CONSTRAINT ck_organization_affiliation_role_assignment_active CHECK (status <> 'ACTIVE' OR activated_at IS NOT NULL), CONSTRAINT ck_organization_affiliation_role_assignment_removed CHECK (status <> 'REMOVED' OR (removed_at IS NOT NULL AND removed_by_account_id IS NOT NULL)), CONSTRAINT ck_organization_affiliation_role_assignment_cancelled CHECK (status <> 'CANCELLED' OR removed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }
    public function down(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_drop_role_assignments'), 'Drop affiliation role assignment history.', 'DROP TABLE organization_affiliation_role_assignments'),new SqlMigrationStep(new MigrationStepId('002_drop_unit_assignments'), 'Drop affiliation unit assignment history.', 'DROP TABLE organization_affiliation_unit_assignments')];
    }
    public function reversible(): bool
    {
        return true;
    }
}
