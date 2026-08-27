<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePasskeyFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011500_create_passkey_foundation');
    }

    public function description(): string
    {
        return 'Create WebAuthn user handles, passkeys and one-time ceremonies.';
    }

    public function dependencies(): array
    {
        return [(new CreateAuthenticationTransactionFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_webauthn_user_handles'),
                'Create random per-account WebAuthn user handles.',
                <<<'SQL'
CREATE TABLE account_webauthn_user_handles (
    account_id BIGINT UNSIGNED NOT NULL,
    user_handle BINARY(32) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_webauthn_user_handles_value (user_handle),
    CONSTRAINT fk_webauthn_user_handles_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_passkey_credentials'),
                'Create public passkey credentials.',
                <<<'SQL'
CREATE TABLE account_passkey_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    credential_id VARBINARY(1024) NOT NULL,
    credential_public_key BLOB NOT NULL,
    signature_counter BIGINT UNSIGNED NOT NULL DEFAULT 0,
    aaguid BINARY(16) NULL,
    transports JSON NOT NULL,
    backup_eligible TINYINT UNSIGNED NOT NULL,
    backup_state TINYINT UNSIGNED NOT NULL,
    user_verification_required TINYINT UNSIGNED NOT NULL DEFAULT 1,
    attestation_format VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    last_used_at DATETIME(6) NULL,
    suspended_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_passkey_credentials_public_id (public_id),
    UNIQUE KEY uq_passkey_credentials_credential_id (credential_id),
    UNIQUE KEY uq_passkey_credentials_account_id (account_id, id),
    KEY ix_passkey_credentials_account_status_created (account_id, status, created_at, id),
    KEY ix_passkey_credentials_status_last_used (status, last_used_at, id),
    CONSTRAINT fk_passkey_credentials_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_passkey_credentials_status CHECK (status IN ('ACTIVE','SUSPENDED','REVOKED')),
    CONSTRAINT ck_passkey_credentials_backup_eligible CHECK (backup_eligible IN (0,1)),
    CONSTRAINT ck_passkey_credentials_backup_state CHECK (backup_state IN (0,1)),
    CONSTRAINT ck_passkey_credentials_uv CHECK (user_verification_required = 1),
    CONSTRAINT ck_passkey_credentials_version CHECK (version >= 1),
    CONSTRAINT ck_passkey_credentials_suspended CHECK (status <> 'SUSPENDED' OR suspended_at IS NOT NULL),
    CONSTRAINT ck_passkey_credentials_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_passkey_credentials_transports CHECK (JSON_TYPE(transports) = 'ARRAY')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_create_account_webauthn_ceremonies'),
                'Create bounded one-time WebAuthn ceremonies.',
                <<<'SQL'
CREATE TABLE account_webauthn_ceremonies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NULL,
    authentication_transaction_id BIGINT UNSIGNED NULL,
    purpose VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    challenge_hash BINARY(32) NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    maximum_attempts SMALLINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_session_purpose_marker TINYINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'PENDING' AND session_id IS NOT NULL, 1, NULL)) STORED,
    active_transaction_purpose_marker TINYINT UNSIGNED
        GENERATED ALWAYS AS (
            IF(status = 'PENDING' AND authentication_transaction_id IS NOT NULL, 1, NULL)
        ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webauthn_ceremonies_public_id (public_id),
    UNIQUE KEY uq_webauthn_ceremonies_challenge_hash (challenge_hash),
    UNIQUE KEY uq_webauthn_ceremonies_active_session (
        session_id, purpose, active_session_purpose_marker
    ),
    UNIQUE KEY uq_webauthn_ceremonies_active_transaction (
        authentication_transaction_id, purpose, active_transaction_purpose_marker
    ),
    KEY ix_webauthn_ceremonies_account_status_created (account_id, status, created_at, id),
    KEY ix_webauthn_ceremonies_session_status_created (session_id, status, created_at, id),
    KEY ix_webauthn_ceremonies_transaction_status_created (
        authentication_transaction_id, status, created_at, id
    ),
    KEY ix_webauthn_ceremonies_status_expiry (status, expires_at, id),
    CONSTRAINT fk_webauthn_ceremonies_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_webauthn_ceremonies_account_session FOREIGN KEY (account_id, session_id)
        REFERENCES user_sessions (account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_webauthn_ceremonies_transaction FOREIGN KEY (authentication_transaction_id)
        REFERENCES account_authentication_transactions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_webauthn_ceremonies_purpose CHECK (
        purpose IN ('PASSKEY_REGISTRATION','PASSKEY_LOGIN','MFA_LOGIN','STEP_UP')
    ),
    CONSTRAINT ck_webauthn_ceremonies_status CHECK (status IN ('PENDING','CONSUMED','EXPIRED','REVOKED')),
    CONSTRAINT ck_webauthn_ceremonies_attempt_count CHECK (attempt_count <= maximum_attempts),
    CONSTRAINT ck_webauthn_ceremonies_max_attempts CHECK (maximum_attempts >= 1),
    CONSTRAINT ck_webauthn_ceremonies_version CHECK (version >= 1),
    CONSTRAINT ck_webauthn_ceremonies_expiry CHECK (expires_at > created_at),
    CONSTRAINT ck_webauthn_ceremonies_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL),
    CONSTRAINT ck_webauthn_ceremonies_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_webauthn_ceremonies'),
                'Drop WebAuthn ceremonies.',
                'DROP TABLE account_webauthn_ceremonies'
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_passkey_credentials'),
                'Drop passkey credentials.',
                'DROP TABLE account_passkey_credentials'
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_drop_account_webauthn_user_handles'),
                'Drop WebAuthn user handles.',
                'DROP TABLE account_webauthn_user_handles'
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
