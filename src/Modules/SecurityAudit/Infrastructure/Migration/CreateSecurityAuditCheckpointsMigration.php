<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateSecurityAuditCheckpointsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012500_create_security_audit_checkpoints');
    }

    public function description(): string
    {
        return 'Create immutable security audit checkpoints and stream-head snapshots.';
    }

    public function dependencies(): array
    {
        return [new MigrationId('20260826012400_create_security_audit_streams')];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_checkpoints'), 'Create immutable audit checkpoint records.', <<<'SQL'
CREATE TABLE security_audit_checkpoints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    checkpoint_number BIGINT UNSIGNED NOT NULL,
    stream_count INT UNSIGNED NOT NULL,
    total_event_count BIGINT UNSIGNED NOT NULL,
    heads_digest BINARY(32) NOT NULL,
    previous_checkpoint_hash BINARY(32) NOT NULL,
    checkpoint_hash BINARY(32) NOT NULL,
    integrity_key_version SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_security_audit_checkpoints_public_id (public_id),
    UNIQUE KEY uq_security_audit_checkpoints_number (checkpoint_number),
    UNIQUE KEY uq_security_audit_checkpoints_hash (checkpoint_hash),
    KEY ix_security_audit_checkpoints_created (created_at, id),
    CONSTRAINT ck_security_audit_checkpoints_number CHECK (checkpoint_number >= 1),
    CONSTRAINT ck_security_audit_checkpoints_stream_count CHECK (stream_count >= 0),
    CONSTRAINT ck_security_audit_checkpoints_event_count CHECK (total_event_count >= 0),
    CONSTRAINT ck_security_audit_checkpoints_key_version CHECK (integrity_key_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_checkpoint_heads'), 'Create immutable checkpoint stream-head snapshots.', <<<'SQL'
CREATE TABLE security_audit_checkpoint_heads (
    checkpoint_id BIGINT UNSIGNED NOT NULL,
    stream_id BIGINT UNSIGNED NOT NULL,
    stream_sequence BIGINT UNSIGNED NOT NULL,
    event_hash BINARY(32) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (checkpoint_id, stream_id),
    KEY ix_security_audit_checkpoint_heads_stream (stream_id, checkpoint_id),
    CONSTRAINT fk_security_audit_checkpoint_heads_checkpoint FOREIGN KEY (checkpoint_id)
        REFERENCES security_audit_checkpoints (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_security_audit_checkpoint_heads_stream FOREIGN KEY (stream_id)
        REFERENCES security_audit_streams (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_security_audit_checkpoint_heads_sequence CHECK (stream_sequence >= 0),
    CONSTRAINT ck_security_audit_checkpoint_heads_hash CHECK (
        (stream_sequence = 0 AND event_hash IS NULL) OR (stream_sequence > 0 AND event_hash IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_protect_checkpoint_updates'), 'Reject checkpoint updates.', <<<'SQL'
CREATE TRIGGER trg_security_audit_checkpoints_no_update
BEFORE UPDATE ON security_audit_checkpoints FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit checkpoints are immutable.'
SQL),
            new SqlMigrationStep(new MigrationStepId('004_protect_checkpoint_deletes'), 'Reject checkpoint deletion.', <<<'SQL'
CREATE TRIGGER trg_security_audit_checkpoints_no_delete
BEFORE DELETE ON security_audit_checkpoints FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit checkpoints are immutable.'
SQL),
            new SqlMigrationStep(new MigrationStepId('005_protect_checkpoint_head_updates'), 'Reject checkpoint head updates.', <<<'SQL'
CREATE TRIGGER trg_security_audit_checkpoint_heads_no_update
BEFORE UPDATE ON security_audit_checkpoint_heads FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit checkpoint heads are immutable.'
SQL),
            new SqlMigrationStep(new MigrationStepId('006_protect_checkpoint_head_deletes'), 'Reject checkpoint head deletion.', <<<'SQL'
CREATE TRIGGER trg_security_audit_checkpoint_heads_no_delete
BEFORE DELETE ON security_audit_checkpoint_heads FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit checkpoint heads are immutable.'
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_head_delete_trigger'), 'Drop checkpoint head delete guard.', 'DROP TRIGGER trg_security_audit_checkpoint_heads_no_delete'),
            new SqlMigrationStep(new MigrationStepId('002_drop_head_update_trigger'), 'Drop checkpoint head update guard.', 'DROP TRIGGER trg_security_audit_checkpoint_heads_no_update'),
            new SqlMigrationStep(new MigrationStepId('003_drop_checkpoint_delete_trigger'), 'Drop checkpoint delete guard.', 'DROP TRIGGER trg_security_audit_checkpoints_no_delete'),
            new SqlMigrationStep(new MigrationStepId('004_drop_checkpoint_update_trigger'), 'Drop checkpoint update guard.', 'DROP TRIGGER trg_security_audit_checkpoints_no_update'),
            new SqlMigrationStep(new MigrationStepId('005_drop_checkpoint_heads'), 'Drop checkpoint heads.', 'DROP TABLE security_audit_checkpoint_heads'),
            new SqlMigrationStep(new MigrationStepId('006_drop_checkpoints'), 'Drop checkpoints.', 'DROP TABLE security_audit_checkpoints'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
