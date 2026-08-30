<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateSecurityAuditStreamsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012400_create_security_audit_streams');
    }

    public function description(): string
    {
        return 'Create keyed, append-only security audit streams and events.';
    }

    public function dependencies(): array
    {
        return [new MigrationId('20260826012300_extend_account_state_security_catalog')];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_streams'), 'Create security audit stream heads.', <<<'SQL'
CREATE TABLE security_audit_streams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    stream_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    stream_key BINARY(32) NOT NULL,
    scope_public_id BINARY(16) NULL,
    last_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_event_hash BINARY(32) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_security_audit_streams_public_id (public_id),
    UNIQUE KEY uq_security_audit_streams_stream_key (stream_key),
    KEY ix_security_audit_streams_type_scope (stream_type, scope_public_id),
    KEY ix_security_audit_streams_updated (updated_at, id),
    CONSTRAINT ck_security_audit_streams_type CHECK (stream_type IN ('PLATFORM','ACCOUNT','WORKSPACE')),
    CONSTRAINT ck_security_audit_streams_sequence CHECK (last_sequence >= 0),
    CONSTRAINT ck_security_audit_streams_version CHECK (version >= 1),
    CONSTRAINT ck_security_audit_streams_scope CHECK (
        (stream_type = 'PLATFORM' AND scope_public_id IS NULL)
        OR (stream_type IN ('ACCOUNT','WORKSPACE') AND scope_public_id IS NOT NULL)
    ),
    CONSTRAINT ck_security_audit_streams_head CHECK (
        (last_sequence = 0 AND last_event_hash IS NULL) OR (last_sequence > 0 AND last_event_hash IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_events'), 'Create immutable hash-chained security audit events.', <<<'SQL'
CREATE TABLE security_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    stream_id BIGINT UNSIGNED NOT NULL,
    sequence_number BIGINT UNSIGNED NOT NULL,
    event_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    severity VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_account_public_id BINARY(16) NULL,
    session_public_id BINARY(16) NULL,
    workspace_public_id BINARY(16) NULL,
    subject_kind VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_public_id BINARY(16) NOT NULL,
    reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    request_id BINARY(16) NULL,
    correlation_id BINARY(16) NULL,
    metadata_canonical_json JSON NOT NULL,
    metadata_hash BINARY(32) NOT NULL,
    previous_event_hash BINARY(32) NOT NULL,
    event_hash BINARY(32) NOT NULL,
    integrity_key_version SMALLINT UNSIGNED NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_security_audit_events_public_id (public_id),
    UNIQUE KEY uq_security_audit_events_stream_sequence (stream_id, sequence_number),
    UNIQUE KEY uq_security_audit_events_hash (event_hash),
    KEY ix_security_audit_events_stream_occurred (stream_id, occurred_at, id),
    KEY ix_security_audit_events_code_occurred (event_code, occurred_at, id),
    KEY ix_security_audit_events_actor_occurred (actor_account_public_id, occurred_at, id),
    KEY ix_security_audit_events_subject_occurred (subject_kind, subject_public_id, occurred_at, id),
    KEY ix_security_audit_events_workspace_occurred (workspace_public_id, occurred_at, id),
    KEY ix_security_audit_events_severity_occurred (severity, occurred_at, id),
    CONSTRAINT fk_security_audit_events_stream FOREIGN KEY (stream_id)
        REFERENCES security_audit_streams (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_security_audit_events_sequence CHECK (sequence_number >= 1),
    CONSTRAINT ck_security_audit_events_severity CHECK (severity IN ('INFO','WARNING','CRITICAL')),
    CONSTRAINT ck_security_audit_events_outcome CHECK (outcome IN ('SUCCESS','DENIED','FAILURE')),
    CONSTRAINT ck_security_audit_events_actor_kind CHECK (actor_kind IN ('ACCOUNT','SYSTEM')),
    CONSTRAINT ck_security_audit_events_actor CHECK (
        (actor_kind = 'ACCOUNT' AND actor_account_public_id IS NOT NULL)
        OR (actor_kind = 'SYSTEM' AND actor_account_public_id IS NULL)
    ),
    CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN (
        'ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET',
        'PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT'
    )),
    CONSTRAINT ck_security_audit_events_metadata_size CHECK (OCTET_LENGTH(metadata_canonical_json) <= 4096),
    CONSTRAINT ck_security_audit_events_key_version CHECK (integrity_key_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_protect_stream_deletion'), 'Reject security audit stream deletion.', <<<'SQL'
CREATE TRIGGER trg_security_audit_streams_no_delete
BEFORE DELETE ON security_audit_streams FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit streams are append-only evidence.'
SQL),
            new SqlMigrationStep(new MigrationStepId('004_protect_event_updates'), 'Reject security audit event updates.', <<<'SQL'
CREATE TRIGGER trg_security_audit_events_no_update
BEFORE UPDATE ON security_audit_events FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit events are immutable.'
SQL),
            new SqlMigrationStep(new MigrationStepId('005_protect_event_deletes'), 'Reject security audit event deletion.', <<<'SQL'
CREATE TRIGGER trg_security_audit_events_no_delete
BEFORE DELETE ON security_audit_events FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit events are immutable.'
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_event_delete_trigger'), 'Drop event delete guard.', 'DROP TRIGGER trg_security_audit_events_no_delete'),
            new SqlMigrationStep(new MigrationStepId('002_drop_event_update_trigger'), 'Drop event update guard.', 'DROP TRIGGER trg_security_audit_events_no_update'),
            new SqlMigrationStep(new MigrationStepId('003_drop_stream_delete_trigger'), 'Drop stream delete guard.', 'DROP TRIGGER trg_security_audit_streams_no_delete'),
            new SqlMigrationStep(new MigrationStepId('004_drop_events'), 'Drop security audit events.', 'DROP TABLE security_audit_events'),
            new SqlMigrationStep(new MigrationStepId('005_drop_streams'), 'Drop security audit streams.', 'DROP TABLE security_audit_streams'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
