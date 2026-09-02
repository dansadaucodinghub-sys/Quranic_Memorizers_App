<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateOrganizationAffiliationsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901040500_create_organization_affiliations');
    }
    public function description(): string
    {
        return 'Create private Organization affiliations and immutable lifecycle history.';
    }
    public function dependencies(): array
    {
        return [(new CreateOrganizationAffiliationCatalogAndSecurityMigration())->id()];
    }
    public function up(): array
    {
        return [
        new SqlMigrationStep(new MigrationStepId('001_create_affiliations'), 'Create tenant-owned Organization affiliations.', <<<'SQL'
CREATE TABLE organization_affiliations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, affiliation_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, person_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, requested_by_account_id BIGINT UNSIGNED NOT NULL,
 responded_by_account_id BIGINT UNSIGNED NULL, response_authority_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL, response_guardianship_id BIGINT UNSIGNED NULL,
 request_expires_at DATETIME(6) NOT NULL, requested_at DATETIME(6) NOT NULL, responded_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, suspended_at DATETIME(6) NULL, ended_at DATETIME(6) NULL, end_reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 open_affiliation_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_affiliations_public_id (public_id), UNIQUE KEY uq_organization_affiliations_code (affiliation_code),
 UNIQUE KEY uq_organization_affiliations_open (workspace_id,organization_id,person_id,open_affiliation_marker),
 UNIQUE KEY uq_organization_affiliations_workspace_organization_id (workspace_id,organization_id,id),
 KEY ix_organization_affiliations_roster (workspace_id,organization_id,status,created_at,id), KEY ix_organization_affiliations_person (person_id,status,created_at,id), KEY ix_organization_affiliations_requester (requested_by_account_id,status,created_at,id), KEY ix_organization_affiliations_expiry (status,request_expires_at,id),
 CONSTRAINT fk_organization_affiliations_organization FOREIGN KEY (workspace_id,organization_id) REFERENCES organizations (workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliations_person FOREIGN KEY (person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliations_requested_by FOREIGN KEY (requested_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliations_responded_by FOREIGN KEY (responded_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliations_guardianship FOREIGN KEY (response_guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_organization_affiliations_status CHECK (status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED','ENDED','DECLINED','WITHDRAWN','EXPIRED')),
 CONSTRAINT ck_organization_affiliations_response_authority CHECK (response_authority_type IS NULL OR response_authority_type IN ('SELF','GUARDIAN')),
 CONSTRAINT ck_organization_affiliations_version CHECK (version >= 1), CONSTRAINT ck_organization_affiliations_expiry CHECK (request_expires_at > requested_at),
 CONSTRAINT ck_organization_affiliations_active CHECK (status <> 'ACTIVE' OR activated_at IS NOT NULL),
 CONSTRAINT ck_organization_affiliations_declined CHECK (status <> 'DECLINED' OR (responded_at IS NOT NULL AND responded_by_account_id IS NOT NULL)),
 CONSTRAINT ck_organization_affiliations_ended CHECK (status <> 'ENDED' OR (ended_at IS NOT NULL AND end_reason_code IS NOT NULL)),
 CONSTRAINT ck_organization_affiliations_self_response CHECK (response_authority_type <> 'SELF' OR (responded_by_account_id IS NOT NULL AND response_guardianship_id IS NULL)),
 CONSTRAINT ck_organization_affiliations_guardian_response CHECK (response_authority_type <> 'GUARDIAN' OR (responded_by_account_id IS NOT NULL AND response_guardianship_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('002_create_status_events'), 'Create immutable affiliation lifecycle history.', <<<'SQL'
CREATE TABLE organization_affiliation_status_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, affiliation_id BIGINT UNSIGNED NOT NULL,
 from_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL, to_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_authority VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, guardianship_id BIGINT UNSIGNED NULL, reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, correlation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_affiliation_status_events_public_id (public_id), KEY ix_organization_affiliation_status_events_affiliation (workspace_id,organization_id,affiliation_id,occurred_at,id), KEY ix_organization_affiliation_status_events_actor (actor_account_id,occurred_at,id), KEY ix_organization_affiliation_status_events_status (to_status,occurred_at,id),
 CONSTRAINT fk_organization_affiliation_status_events_affiliation FOREIGN KEY (workspace_id,organization_id,affiliation_id) REFERENCES organization_affiliations (workspace_id,organization_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_status_events_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_organization_affiliation_status_events_guardianship FOREIGN KEY (guardianship_id) REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_organization_affiliation_status_events_authority CHECK (actor_authority IN ('ORGANIZATION_MANAGER','SELF','GUARDIAN','SYSTEM')),
 CONSTRAINT ck_organization_affiliation_status_events_system_actor CHECK (actor_authority <> 'SYSTEM' OR actor_account_id IS NULL),
 CONSTRAINT ck_organization_affiliation_status_events_non_system_actor CHECK (actor_authority = 'SYSTEM' OR actor_account_id IS NOT NULL),
 CONSTRAINT ck_organization_affiliation_status_events_guardian CHECK (actor_authority <> 'GUARDIAN' OR guardianship_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('003_protect_status_events_update'), 'Prevent lifecycle-history updates.', "CREATE TRIGGER trg_organization_affiliation_status_events_no_update BEFORE UPDATE ON organization_affiliation_status_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Organization affiliation lifecycle history is immutable'"),
        new SqlMigrationStep(new MigrationStepId('004_protect_status_events_delete'), 'Prevent lifecycle-history deletion.', "CREATE TRIGGER trg_organization_affiliation_status_events_no_delete BEFORE DELETE ON organization_affiliation_status_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Organization affiliation lifecycle history is immutable'"),
        ];
    }
    public function down(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_drop_status_event_delete_trigger'), 'Drop affiliation lifecycle delete trigger.', 'DROP TRIGGER trg_organization_affiliation_status_events_no_delete'),new SqlMigrationStep(new MigrationStepId('002_drop_status_event_update_trigger'), 'Drop affiliation lifecycle update trigger.', 'DROP TRIGGER trg_organization_affiliation_status_events_no_update'),new SqlMigrationStep(new MigrationStepId('003_drop_status_events'), 'Drop affiliation lifecycle history.', 'DROP TABLE organization_affiliation_status_events'),new SqlMigrationStep(new MigrationStepId('004_drop_affiliations'), 'Drop Organization affiliations.', 'DROP TABLE organization_affiliations')];
    }
    public function reversible(): bool
    {
        return true;
    }
}
