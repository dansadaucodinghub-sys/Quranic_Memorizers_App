<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePrivilegedAccessRequestFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012100_create_privileged_access_request_foundation');
    }

    public function description(): string
    {
        return 'Create policy-governed privileged-access requests, permission snapshots and approvals.';
    }

    public function dependencies(): array
    {
        return [(new ExtendPrivilegedAccessSecurityCatalogMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_permission_policies'), 'Create seed-managed privileged permission eligibility policies.', <<<'SQL'
CREATE TABLE privileged_access_permission_policies (
    access_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    permission_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (access_type, permission_id),
    KEY ix_privileged_policy_scope_type_status (permission_scope_type, access_type, status),
    KEY ix_privileged_policy_status_updated (status, updated_at),
    CONSTRAINT fk_privileged_policy_permission FOREIGN KEY (permission_id, permission_scope_type)
        REFERENCES authorization_permissions (id, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_policy_access_type CHECK (
        access_type IN ('TEMPORARY_PRIVILEGE','SUPPORT_ACCESS','BREAK_GLASS')
    ),
    CONSTRAINT ck_privileged_policy_status CHECK (status IN ('ACTIVE','RETIRED')),
    CONSTRAINT ck_privileged_policy_retired CHECK (
        (status = 'ACTIVE' AND retired_at IS NULL) OR (status = 'RETIRED' AND retired_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_requests'), 'Create self-requested, versioned privileged access requests.', <<<'SQL'
CREATE TABLE privileged_access_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    submission_id BINARY(16) NOT NULL,
    access_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id BIGINT UNSIGNED NULL,
    subject_account_id BIGINT UNSIGNED NOT NULL,
    subject_membership_id BIGINT UNSIGNED NULL,
    requested_by_account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_duration_seconds INT UNSIGNED NOT NULL,
    approved_duration_seconds INT UNSIGNED NULL,
    justification TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    reference_code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    requested_locale VARCHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_expires_at DATETIME(6) NOT NULL,
    approved_at DATETIME(6) NULL,
    activated_at DATETIME(6) NULL,
    rejected_at DATETIME(6) NULL,
    cancelled_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    expired_at DATETIME(6) NULL,
    review_required_at DATETIME(6) NULL,
    closed_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_privileged_requests_public_id (public_id),
    UNIQUE KEY uq_privileged_requests_submission_id (submission_id),
    UNIQUE KEY uq_privileged_requests_type_scope (id, access_type, scope_type),
    KEY ix_privileged_requests_subject_status_created (subject_account_id, status, created_at, id),
    KEY ix_privileged_requests_requested_by_status_created (requested_by_account_id, status, created_at, id),
    KEY ix_privileged_requests_workspace_status_created (workspace_id, status, created_at, id),
    KEY ix_privileged_requests_type_status_expiry (access_type, status, request_expires_at, id),
    KEY ix_privileged_requests_status_updated (status, updated_at, id),
    CONSTRAINT fk_privileged_requests_subject FOREIGN KEY (subject_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_requests_requested_by FOREIGN KEY (requested_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_requests_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_requests_subject_membership FOREIGN KEY (
        workspace_id, subject_account_id, subject_membership_id
    ) REFERENCES workspace_memberships (workspace_id, user_account_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_requests_access_type CHECK (
        access_type IN ('TEMPORARY_PRIVILEGE','SUPPORT_ACCESS','BREAK_GLASS')
    ),
    CONSTRAINT ck_privileged_requests_scope_type CHECK (scope_type IN ('PLATFORM','WORKSPACE')),
    CONSTRAINT ck_privileged_requests_status CHECK (status IN (
        'REQUESTED','PARTIALLY_APPROVED','APPROVED','ACTIVE','REJECTED','CANCELLED','REVOKED','EXPIRED',
        'REVIEW_REQUIRED','CLOSED'
    )),
    CONSTRAINT ck_privileged_requests_version CHECK (version >= 1),
    CONSTRAINT ck_privileged_requests_requested_duration CHECK (requested_duration_seconds >= 1),
    CONSTRAINT ck_privileged_requests_approved_duration CHECK (
        approved_duration_seconds IS NULL OR (
            approved_duration_seconds >= 1 AND approved_duration_seconds <= requested_duration_seconds
        )
    ),
    CONSTRAINT ck_privileged_requests_self_requested CHECK (subject_account_id = requested_by_account_id),
    CONSTRAINT ck_privileged_requests_scope_context CHECK (
        (scope_type = 'PLATFORM' AND workspace_id IS NULL AND subject_membership_id IS NULL)
        OR (scope_type = 'WORKSPACE' AND workspace_id IS NOT NULL)
    ),
    CONSTRAINT ck_privileged_requests_type_context CHECK (
        (access_type <> 'TEMPORARY_PRIVILEGE' OR scope_type <> 'WORKSPACE' OR subject_membership_id IS NOT NULL)
        AND (access_type <> 'SUPPORT_ACCESS' OR (
            scope_type = 'WORKSPACE' AND subject_membership_id IS NULL AND reference_code IS NOT NULL
        ))
        AND (access_type <> 'BREAK_GLASS' OR reference_code IS NOT NULL)
    ),
    CONSTRAINT ck_privileged_requests_locale CHECK (requested_locale IN ('en','ar')),
    CONSTRAINT ck_privileged_requests_approved CHECK (status <> 'APPROVED' OR approved_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_active CHECK (status <> 'ACTIVE' OR activated_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_rejected CHECK (status <> 'REJECTED' OR rejected_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_cancelled CHECK (status <> 'CANCELLED' OR cancelled_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_expired CHECK (status <> 'EXPIRED' OR expired_at IS NOT NULL),
    CONSTRAINT ck_privileged_requests_review_required CHECK (
        status <> 'REVIEW_REQUIRED' OR review_required_at IS NOT NULL
    ),
    CONSTRAINT ck_privileged_requests_closed CHECK (status <> 'CLOSED' OR closed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_request_permissions'), 'Create immutable exact permission snapshots.', <<<'SQL'
CREATE TABLE privileged_access_request_permissions (
    request_id BIGINT UNSIGNED NOT NULL,
    request_access_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    permission_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (request_id, permission_id),
    KEY ix_privileged_request_permissions_permission_request (permission_id, request_id),
    KEY ix_privileged_request_permissions_type_scope_request (request_access_type, request_scope_type, request_id),
    CONSTRAINT fk_privileged_request_permissions_request FOREIGN KEY (
        request_id, request_access_type, request_scope_type
    ) REFERENCES privileged_access_requests (id, access_type, scope_type)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_request_permissions_permission FOREIGN KEY (permission_id, permission_scope_type)
        REFERENCES authorization_permissions (id, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_request_permissions_scope CHECK (request_scope_type = permission_scope_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_create_approvals'), 'Create immutable platform and workspace approval records.', <<<'SQL'
CREATE TABLE privileged_access_approvals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    approval_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decision VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    approver_account_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NULL,
    approver_membership_id BIGINT UNSIGNED NULL,
    assurance_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    step_up_grant_id BIGINT UNSIGNED NOT NULL,
    approved_duration_seconds INT UNSIGNED NOT NULL,
    reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decided_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_privileged_approvals_public_id (public_id),
    UNIQUE KEY uq_privileged_approvals_request_type (request_id, approval_type),
    KEY ix_privileged_approvals_approver_decided (approver_account_id, decided_at, id),
    KEY ix_privileged_approvals_request_decided (request_id, decided_at, id),
    KEY ix_privileged_approvals_workspace_decided (workspace_id, decided_at, id),
    CONSTRAINT fk_privileged_approvals_request FOREIGN KEY (request_id)
        REFERENCES privileged_access_requests (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_approvals_approver FOREIGN KEY (approver_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_approvals_membership FOREIGN KEY (
        workspace_id, approver_account_id, approver_membership_id
    ) REFERENCES workspace_memberships (workspace_id, user_account_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_approvals_step_up FOREIGN KEY (step_up_grant_id)
        REFERENCES account_step_up_grants (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_approvals_type CHECK (approval_type IN ('PLATFORM','WORKSPACE')),
    CONSTRAINT ck_privileged_approvals_decision CHECK (decision IN ('APPROVED','REJECTED')),
    CONSTRAINT ck_privileged_approvals_assurance CHECK (
        assurance_level IN ('PRIMARY','MULTI_FACTOR','PHISHING_RESISTANT')
    ),
    CONSTRAINT ck_privileged_approvals_duration CHECK (approved_duration_seconds >= 1),
    CONSTRAINT ck_privileged_approvals_scope_context CHECK (
        (approval_type = 'PLATFORM' AND workspace_id IS NULL AND approver_membership_id IS NULL)
        OR (approval_type = 'WORKSPACE' AND workspace_id IS NOT NULL AND approver_membership_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_approvals'), 'Drop privileged access approval records.', 'DROP TABLE privileged_access_approvals'),
            new SqlMigrationStep(new MigrationStepId('002_drop_request_permissions'), 'Drop privileged access permission snapshots.', 'DROP TABLE privileged_access_request_permissions'),
            new SqlMigrationStep(new MigrationStepId('003_drop_requests'), 'Drop privileged access requests.', 'DROP TABLE privileged_access_requests'),
            new SqlMigrationStep(new MigrationStepId('004_drop_permission_policies'), 'Drop privileged permission eligibility policies.', 'DROP TABLE privileged_access_permission_policies'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
