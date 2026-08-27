<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateTotpRecoveryCodeFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011400_create_totp_recovery_code_foundation');
    }

    public function description(): string
    {
        return 'Create TOTP authenticators and one-time recovery codes.';
    }

    public function dependencies(): array
    {
        return [(new CreateAuthenticationTransactionFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_totp_authenticators'),
                'Create encrypted TOTP authenticators.',
                <<<'SQL'
CREATE TABLE account_totp_authenticators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    secret_ciphertext BLOB NOT NULL,
    secret_nonce BINARY(24) NOT NULL,
    encryption_key_version INT UNSIGNED NOT NULL,
    algorithm VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    digits TINYINT UNSIGNED NOT NULL,
    period_seconds SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_accepted_counter BIGINT UNSIGNED NULL,
    enrollment_expires_at DATETIME(6) NULL,
    confirmed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_account_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'ACTIVE', account_id, NULL)) STORED,
    pending_account_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'PENDING', account_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_totp_authenticators_public_id (public_id),
    UNIQUE KEY uq_totp_authenticators_active_account (active_account_key),
    UNIQUE KEY uq_totp_authenticators_pending_account (pending_account_key),
    KEY ix_totp_authenticators_account_status_created (account_id, status, created_at, id),
    KEY ix_totp_authenticators_status_expiry (status, enrollment_expires_at, id),
    CONSTRAINT fk_totp_authenticators_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_totp_authenticators_algorithm CHECK (algorithm = 'SHA1'),
    CONSTRAINT ck_totp_authenticators_digits CHECK (digits = 6),
    CONSTRAINT ck_totp_authenticators_period CHECK (period_seconds = 30),
    CONSTRAINT ck_totp_authenticators_status CHECK (status IN ('PENDING','ACTIVE','REVOKED')),
    CONSTRAINT ck_totp_authenticators_key_version CHECK (encryption_key_version >= 1),
    CONSTRAINT ck_totp_authenticators_version CHECK (version >= 1),
    CONSTRAINT ck_totp_authenticators_pending CHECK (
        status <> 'PENDING' OR enrollment_expires_at IS NOT NULL
    ),
    CONSTRAINT ck_totp_authenticators_active CHECK (status <> 'ACTIVE' OR confirmed_at IS NOT NULL),
    CONSTRAINT ck_totp_authenticators_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_recovery_code_sets'),
                'Create recovery-code sets.',
                <<<'SQL'
CREATE TABLE account_recovery_code_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    generated_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    exhausted_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_account_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'ACTIVE', account_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_code_sets_public_id (public_id),
    UNIQUE KEY uq_recovery_code_sets_active_account (active_account_key),
    KEY ix_recovery_code_sets_account_status_generated (account_id, status, generated_at, id),
    KEY ix_recovery_code_sets_status_updated (status, updated_at, id),
    CONSTRAINT fk_recovery_code_sets_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_recovery_code_sets_status CHECK (status IN ('ACTIVE','REVOKED','EXHAUSTED')),
    CONSTRAINT ck_recovery_code_sets_version CHECK (version >= 1),
    CONSTRAINT ck_recovery_code_sets_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_recovery_code_sets_exhausted CHECK (status <> 'EXHAUSTED' OR exhausted_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_create_account_recovery_codes'),
                'Create hashed recovery codes.',
                <<<'SQL'
CREATE TABLE account_recovery_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    recovery_code_set_id BIGINT UNSIGNED NOT NULL,
    code_hash BINARY(32) NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    consumed_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_codes_public_id (public_id),
    UNIQUE KEY uq_recovery_codes_hash (code_hash),
    UNIQUE KEY uq_recovery_codes_set_position (recovery_code_set_id, position),
    KEY ix_recovery_codes_set_status_position (recovery_code_set_id, status, position),
    KEY ix_recovery_codes_status_updated (status, updated_at, id),
    CONSTRAINT fk_recovery_codes_set FOREIGN KEY (recovery_code_set_id)
        REFERENCES account_recovery_code_sets (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_recovery_codes_position CHECK (position >= 1),
    CONSTRAINT ck_recovery_codes_status CHECK (status IN ('ACTIVE','CONSUMED')),
    CONSTRAINT ck_recovery_codes_version CHECK (version >= 1),
    CONSTRAINT ck_recovery_codes_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_recovery_codes'),
                'Drop recovery codes.',
                'DROP TABLE account_recovery_codes'
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_recovery_code_sets'),
                'Drop recovery-code sets.',
                'DROP TABLE account_recovery_code_sets'
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_drop_account_totp_authenticators'),
                'Drop TOTP authenticators.',
                'DROP TABLE account_totp_authenticators'
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
