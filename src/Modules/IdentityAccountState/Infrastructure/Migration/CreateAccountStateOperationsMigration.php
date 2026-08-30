<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateAccountStateOperationsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012600_create_account_state_operations');
    }

    public function description(): string
    {
        return 'Create immutable account suspension and reactivation evidence.';
    }

    public function dependencies(): array
    {
        return [
            new MigrationId('20260826012300_extend_account_state_security_catalog'),
            new MigrationId('20260826012400_create_security_audit_streams'),
        ];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_operations'), 'Create append-only account-state operation evidence.', <<<'SQL'
CREATE TABLE account_state_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    submission_id BINARY(16) NOT NULL,
    request_fingerprint BINARY(32) NOT NULL,
    operation_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_account_id BIGINT UNSIGNED NOT NULL,
    actor_account_id BIGINT UNSIGNED NOT NULL,
    previous_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    new_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    justification TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    reference_code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    step_up_grant_id BIGINT UNSIGNED NOT NULL,
    audit_event_public_id BINARY(16) NOT NULL,
    target_account_version_before INT UNSIGNED NOT NULL,
    target_account_version_after INT UNSIGNED NOT NULL,
    correlation_id BINARY(16) NULL,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_state_operations_public_id (public_id),
    UNIQUE KEY uq_account_state_operations_submission_id (submission_id),
    UNIQUE KEY uq_account_state_operations_audit_event (audit_event_public_id),
    KEY ix_account_state_operations_target_occurred (target_account_id, occurred_at, id),
    KEY ix_account_state_operations_actor_occurred (actor_account_id, occurred_at, id),
    KEY ix_account_state_operations_type_occurred (operation_type, occurred_at, id),
    CONSTRAINT fk_account_state_operations_target FOREIGN KEY (target_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_account_state_operations_actor FOREIGN KEY (actor_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_account_state_operations_step_up FOREIGN KEY (step_up_grant_id)
        REFERENCES account_step_up_grants (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_account_state_operations_audit_event FOREIGN KEY (audit_event_public_id)
        REFERENCES security_audit_events (public_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_account_state_operations_type CHECK (operation_type IN ('SUSPEND','REACTIVATE')),
    CONSTRAINT ck_account_state_operations_not_self CHECK (actor_account_id <> target_account_id),
    CONSTRAINT ck_account_state_operations_before_version CHECK (target_account_version_before >= 1),
    CONSTRAINT ck_account_state_operations_after_version CHECK (
        target_account_version_after = target_account_version_before + 1
    ),
    CONSTRAINT ck_account_state_operations_transition CHECK (
        (operation_type = 'SUSPEND' AND previous_status = 'ACTIVE' AND new_status = 'SUSPENDED')
        OR (operation_type = 'REACTIVATE' AND previous_status = 'SUSPENDED' AND new_status = 'ACTIVE')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_protect_operation_updates'), 'Reject account-state operation updates.', <<<'SQL'
CREATE TRIGGER trg_account_state_operations_no_update
BEFORE UPDATE ON account_state_operations FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account-state operations are immutable.'
SQL),
            new SqlMigrationStep(new MigrationStepId('003_protect_operation_deletes'), 'Reject account-state operation deletion.', <<<'SQL'
CREATE TRIGGER trg_account_state_operations_no_delete
BEFORE DELETE ON account_state_operations FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account-state operations are immutable.'
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_drop_operation_delete_trigger'), 'Drop account-state delete guard.', 'DROP TRIGGER trg_account_state_operations_no_delete'),
            new SqlMigrationStep(new MigrationStepId('002_drop_operation_update_trigger'), 'Drop account-state update guard.', 'DROP TRIGGER trg_account_state_operations_no_update'),
            new SqlMigrationStep(new MigrationStepId('003_drop_operations'), 'Drop account-state operation evidence.', 'DROP TABLE account_state_operations'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
