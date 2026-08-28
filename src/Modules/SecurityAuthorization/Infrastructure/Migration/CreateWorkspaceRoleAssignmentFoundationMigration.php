<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration;

use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateWorkspaceRoleAssignmentFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011800_create_workspace_role_assignment_foundation');
    }

    public function description(): string
    {
        return 'Create workspace role-assignment foundation.';
    }

    public function dependencies(): array
    {
        return [
            (new CreatePlatformRoleAssignmentFoundationMigration())->id(),
            (new CreateWorkspaceMembershipsMigration())->id(),
        ];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_workspace_role_assignments'),
            'Create exact-tenant workspace membership role assignments with preserved history.',
            <<<'SQL'
CREATE TABLE workspace_role_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    role_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    assigned_by_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assigned_by_account_id BIGINT UNSIGNED NULL,
    assignment_reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assigned_at DATETIME(6) NOT NULL,
    revoked_by_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    revoked_by_account_id BIGINT UNSIGNED NULL,
    revocation_reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
    revoked_at DATETIME(6) NULL,
    correlation_id BINARY(16) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_assignment_marker TINYINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'ACTIVE', 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workspace_role_assignments_public_id (public_id),
    UNIQUE KEY uq_workspace_role_assignments_active (
        workspace_id, membership_id, role_id, active_assignment_marker
    ),
    KEY ix_workspace_role_assignments_membership (workspace_id, membership_id, status, id),
    KEY ix_workspace_role_assignments_role (workspace_id, role_id, status, id),
    KEY ix_workspace_role_assignments_history (membership_id, status, assigned_at, id),
    KEY ix_workspace_role_assignments_status_updated (status, updated_at, id),
    CONSTRAINT fk_workspace_role_assignments_membership FOREIGN KEY (workspace_id, membership_id)
        REFERENCES workspace_memberships (workspace_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_workspace_role_assignments_role FOREIGN KEY (role_id, role_scope_type)
        REFERENCES authorization_roles (id, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_workspace_role_assignments_assigned_by FOREIGN KEY (assigned_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_workspace_role_assignments_revoked_by FOREIGN KEY (revoked_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_workspace_role_assignments_scope CHECK (role_scope_type = 'WORKSPACE'),
    CONSTRAINT ck_workspace_role_assignments_status CHECK (status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_workspace_role_assignments_version CHECK (version >= 1),
    CONSTRAINT ck_workspace_role_assignments_assigned_kind CHECK (assigned_by_kind IN ('ACCOUNT','SYSTEM')),
    CONSTRAINT ck_workspace_role_assignments_revoked_kind CHECK (
        revoked_by_kind IS NULL OR revoked_by_kind IN ('ACCOUNT','SYSTEM')
    ),
    CONSTRAINT ck_workspace_role_assignments_assignment_reason CHECK (assignment_reason_code IN (
        'SECURITY_ADMINISTRATION','SYSTEM_WORKSPACE_OWNER_INITIALIZATION','SYSTEM_PLATFORM_BOOTSTRAP',
        'SECURITY_RESPONSE','MEMBERSHIP_STATE_CHANGE','ROLE_RETIRED'
    )),
    CONSTRAINT ck_workspace_role_assignments_revocation_reason CHECK (
        revocation_reason_code IS NULL OR revocation_reason_code IN (
            'SECURITY_ADMINISTRATION','SYSTEM_WORKSPACE_OWNER_INITIALIZATION','SYSTEM_PLATFORM_BOOTSTRAP',
            'SECURITY_RESPONSE','MEMBERSHIP_STATE_CHANGE','ROLE_RETIRED'
        )
    ),
    CONSTRAINT ck_workspace_role_assignments_assigned_actor CHECK (
        (assigned_by_kind = 'ACCOUNT' AND assigned_by_account_id IS NOT NULL)
        OR (assigned_by_kind = 'SYSTEM' AND assigned_by_account_id IS NULL)
    ),
    CONSTRAINT ck_workspace_role_assignments_revocation_state CHECK (
        (status = 'ACTIVE' AND revoked_by_kind IS NULL AND revoked_by_account_id IS NULL
            AND revocation_reason_code IS NULL AND revoked_at IS NULL)
        OR (status = 'REVOKED' AND revoked_by_kind IS NOT NULL AND revocation_reason_code IS NOT NULL
            AND revoked_at IS NOT NULL
            AND ((revoked_by_kind = 'ACCOUNT' AND revoked_by_account_id IS NOT NULL)
                OR (revoked_by_kind = 'SYSTEM' AND revoked_by_account_id IS NULL)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_workspace_role_assignments'),
            'Drop workspace role assignments.',
            'DROP TABLE workspace_role_assignments',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
