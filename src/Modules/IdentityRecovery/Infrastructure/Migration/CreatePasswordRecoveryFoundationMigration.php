<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePasswordRecoveryFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011000_create_password_recovery_foundation');
    }

    public function description(): string
    {
        return 'Create password recovery challenges and append-only recovery events.';
    }

    public function dependencies(): array
    {
        return [(new ExtendIdentityRecoveryConstraintsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_password_recovery_challenges'),
                'Create password recovery challenges.',
                <<<'SQL'
CREATE TABLE account_password_recovery_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    account_email_address_id BIGINT UNSIGNED NOT NULL,
    token_hash BINARY(32) NOT NULL,
    requested_locale VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    maximum_attempts SMALLINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    expired_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_account_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'PENDING', account_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_recovery_public_id (public_id),
    UNIQUE KEY uq_password_recovery_token_hash (token_hash),
    UNIQUE KEY uq_password_recovery_active_account (active_account_key),
    KEY ix_password_recovery_account_status_created (account_id, status, created_at, id),
    KEY ix_password_recovery_status_expiry (status, expires_at, id),
    KEY ix_password_recovery_email_created (account_email_address_id, created_at, id),
    CONSTRAINT fk_password_recovery_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_password_recovery_account_email FOREIGN KEY (account_id, account_email_address_id)
        REFERENCES account_email_addresses (user_account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_password_recovery_locale CHECK (requested_locale IN ('en','ar')),
    CONSTRAINT ck_password_recovery_status CHECK (status IN ('PENDING','CONSUMED','EXPIRED','REVOKED')),
    CONSTRAINT ck_password_recovery_attempts CHECK (attempt_count <= maximum_attempts),
    CONSTRAINT ck_password_recovery_maximum CHECK (maximum_attempts >= 1),
    CONSTRAINT ck_password_recovery_version CHECK (version >= 1),
    CONSTRAINT ck_password_recovery_expiry CHECK (expires_at > created_at),
    CONSTRAINT ck_password_recovery_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL),
    CONSTRAINT ck_password_recovery_expired CHECK (status <> 'EXPIRED' OR expired_at IS NOT NULL),
    CONSTRAINT ck_password_recovery_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_password_recovery_events'),
                'Create append-only password recovery history.',
                <<<'SQL'
CREATE TABLE account_password_recovery_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    challenge_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_number SMALLINT UNSIGNED NULL,
    failure_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    correlation_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_recovery_events_public_id (public_id),
    KEY ix_password_recovery_events_challenge (challenge_id, occurred_at, id),
    KEY ix_password_recovery_events_account (account_id, occurred_at, id),
    KEY ix_password_recovery_events_type (event_type, occurred_at, id),
    CONSTRAINT fk_password_recovery_events_challenge FOREIGN KEY (challenge_id)
        REFERENCES account_password_recovery_challenges (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_password_recovery_events_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_password_recovery_events_type CHECK (
        event_type IN ('REQUESTED','DELIVERY_FAILED','TOKEN_REJECTED','EXPIRED','REVOKED','COMPLETED')
    ),
    CONSTRAINT ck_password_recovery_events_attempt CHECK (attempt_number IS NULL OR attempt_number >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_password_recovery_events'),
                'Drop password recovery history.',
                'DROP TABLE account_password_recovery_events',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_password_recovery_challenges'),
                'Drop password recovery challenges.',
                'DROP TABLE account_password_recovery_challenges',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
