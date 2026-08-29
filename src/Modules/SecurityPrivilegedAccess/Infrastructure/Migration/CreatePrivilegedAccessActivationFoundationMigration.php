<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePrivilegedAccessActivationFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012200_create_privileged_access_activation_foundation');
    }

    public function description(): string
    {
        return 'Create session-bound privileged activations, append-only lifecycle events and reviews.';
    }

    public function dependencies(): array
    {
        return [(new CreatePrivilegedAccessRequestFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_activations'), 'Create one-active-per-account and session privileged activations.', <<<'SQL'
CREATE TABLE privileged_access_activations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    access_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_account_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NULL,
    assurance_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    activated_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    ended_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
    tenant_context_version_at_activation BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_session_marker TINYINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'ACTIVE', 1, NULL)) STORED,
    active_subject_marker TINYINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'ACTIVE', 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_privileged_activations_public_id (public_id),
    UNIQUE KEY uq_privileged_activations_request (request_id),
    UNIQUE KEY uq_privileged_activations_active_session (session_id, active_session_marker),
    UNIQUE KEY uq_privileged_activations_active_subject (subject_account_id, active_subject_marker),
    KEY ix_privileged_activations_subject_status_expiry (subject_account_id, status, expires_at, id),
    KEY ix_privileged_activations_session_status_expiry (session_id, status, expires_at, id),
    KEY ix_privileged_activations_workspace_status_expiry (workspace_id, status, expires_at, id),
    KEY ix_privileged_activations_type_status_expiry (access_type, status, expires_at, id),
    KEY ix_privileged_activations_status_expiry (status, expires_at, id),
    CONSTRAINT fk_privileged_activations_request FOREIGN KEY (request_id, access_type, scope_type)
        REFERENCES privileged_access_requests (id, access_type, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_activations_account_session FOREIGN KEY (subject_account_id, session_id)
        REFERENCES user_sessions (account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_activations_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_activations_type CHECK (
        access_type IN ('TEMPORARY_PRIVILEGE','SUPPORT_ACCESS','BREAK_GLASS')
    ),
    CONSTRAINT ck_privileged_activations_scope CHECK (scope_type IN ('PLATFORM','WORKSPACE')),
    CONSTRAINT ck_privileged_activations_status CHECK (status IN ('ACTIVE','ENDED','EXPIRED','REVOKED')),
    CONSTRAINT ck_privileged_activations_assurance CHECK (
        assurance_level IN ('PRIMARY','MULTI_FACTOR','PHISHING_RESISTANT')
    ),
    CONSTRAINT ck_privileged_activations_version CHECK (version >= 1),
    CONSTRAINT ck_privileged_activations_context_version CHECK (tenant_context_version_at_activation >= 1),
    CONSTRAINT ck_privileged_activations_expiry CHECK (expires_at > activated_at),
    CONSTRAINT ck_privileged_activations_scope_context CHECK (
        (scope_type = 'PLATFORM' AND workspace_id IS NULL)
        OR (scope_type = 'WORKSPACE' AND workspace_id IS NOT NULL)
    ),
    CONSTRAINT ck_privileged_activations_ended CHECK (status <> 'ENDED' OR ended_at IS NOT NULL),
    CONSTRAINT ck_privileged_activations_revoked CHECK (
        status <> 'REVOKED' OR (revoked_at IS NOT NULL AND revoke_reason_code IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_events'), 'Create append-only privileged access lifecycle events.', <<<'SQL'
CREATE TABLE privileged_access_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    activation_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_account_id BIGINT UNSIGNED NULL,
    reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id BINARY(16) NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_privileged_events_public_id (public_id),
    KEY ix_privileged_events_request_occurred (request_id, occurred_at, id),
    KEY ix_privileged_events_activation_occurred (activation_id, occurred_at, id),
    KEY ix_privileged_events_type_occurred (event_type, occurred_at, id),
    CONSTRAINT fk_privileged_events_request FOREIGN KEY (request_id)
        REFERENCES privileged_access_requests (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_events_activation FOREIGN KEY (activation_id)
        REFERENCES privileged_access_activations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_events_actor FOREIGN KEY (actor_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_events_type CHECK (event_type IN (
        'REQUESTED','PARTIALLY_APPROVED','APPROVED','REJECTED','CANCELLED','ACTIVATED','ENDED','REVOKED',
        'EXPIRED','REVIEW_CREATED','REVIEW_OVERDUE','REVIEW_COMPLETED'
    )),
    CONSTRAINT ck_privileged_events_actor_kind CHECK (actor_kind IN ('ACCOUNT','SYSTEM')),
    CONSTRAINT ck_privileged_events_actor_context CHECK (
        (actor_kind = 'ACCOUNT' AND actor_account_id IS NOT NULL)
        OR (actor_kind = 'SYSTEM' AND actor_account_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_create_reviews'), 'Create one immutable-completion post-use review per required activation.', <<<'SQL'
CREATE TABLE privileged_access_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    activation_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    due_at DATETIME(6) NOT NULL,
    reviewer_account_id BIGINT UNSIGNED NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
    review_summary VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
    reviewed_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_privileged_reviews_public_id (public_id),
    UNIQUE KEY uq_privileged_reviews_activation (activation_id),
    KEY ix_privileged_reviews_status_due (status, due_at, id),
    KEY ix_privileged_reviews_reviewer_reviewed (reviewer_account_id, reviewed_at, id),
    KEY ix_privileged_reviews_request_created (request_id, created_at, id),
    CONSTRAINT fk_privileged_reviews_request FOREIGN KEY (request_id)
        REFERENCES privileged_access_requests (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_reviews_activation FOREIGN KEY (activation_id)
        REFERENCES privileged_access_activations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privileged_reviews_reviewer FOREIGN KEY (reviewer_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_privileged_reviews_status CHECK (status IN ('PENDING','OVERDUE','COMPLETED')),
    CONSTRAINT ck_privileged_reviews_outcome CHECK (
        outcome IS NULL OR outcome IN ('ACCEPTED_USE','CONCERN','ESCALATED')
    ),
    CONSTRAINT ck_privileged_reviews_version CHECK (version >= 1),
    CONSTRAINT ck_privileged_reviews_due CHECK (due_at > created_at),
    CONSTRAINT ck_privileged_reviews_completed CHECK (
        status <> 'COMPLETED' OR (reviewer_account_id IS NOT NULL AND outcome IS NOT NULL AND reviewed_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_reviews'), 'Drop privileged access reviews.', 'DROP TABLE privileged_access_reviews'),
            new SqlMigrationStep(new MigrationStepId('002_drop_events'), 'Drop privileged access events.', 'DROP TABLE privileged_access_events'),
            new SqlMigrationStep(new MigrationStepId('003_drop_activations'), 'Drop privileged access activations.', 'DROP TABLE privileged_access_activations'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
