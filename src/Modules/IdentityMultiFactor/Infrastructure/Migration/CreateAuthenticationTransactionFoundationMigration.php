<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateAuthenticationTransactionFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011300_create_authentication_transaction_foundation');
    }

    public function description(): string
    {
        return 'Create authentication transactions, step-up grants and MFA policy.';
    }

    public function dependencies(): array
    {
        return [(new ExtendIdentityMultiFactorConstraintsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_authentication_transactions'),
                'Create bounded pre-authentication transactions.',
                <<<'SQL'
CREATE TABLE account_authentication_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    secret_hash BINARY(32) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NULL,
    purpose VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_action VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL,
    primary_authentication_method VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    allowed_methods JSON NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    maximum_attempts SMALLINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_session_purpose_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'PENDING' AND session_id IS NOT NULL, session_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_transactions_public_id (public_id),
    UNIQUE KEY uq_auth_transactions_secret_hash (secret_hash),
    UNIQUE KEY uq_auth_transactions_active_session_purpose (active_session_purpose_key, purpose),
    KEY ix_auth_transactions_account_status_created (account_id, status, created_at, id),
    KEY ix_auth_transactions_session_status_created (session_id, status, created_at, id),
    KEY ix_auth_transactions_status_expiry (status, expires_at, id),
    KEY ix_auth_transactions_purpose_status_created (purpose, status, created_at, id),
    CONSTRAINT fk_auth_transactions_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auth_transactions_account_session FOREIGN KEY (account_id, session_id)
        REFERENCES user_sessions (account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_auth_transactions_purpose CHECK (purpose IN ('LOGIN_MFA','PASSKEY_LOGIN','STEP_UP')),
    CONSTRAINT ck_auth_transactions_status CHECK (status IN ('PENDING','COMPLETED','EXPIRED','REVOKED')),
    CONSTRAINT ck_auth_transactions_attempt_count CHECK (attempt_count <= maximum_attempts),
    CONSTRAINT ck_auth_transactions_max_attempts CHECK (maximum_attempts >= 1),
    CONSTRAINT ck_auth_transactions_version CHECK (version >= 1),
    CONSTRAINT ck_auth_transactions_expiry CHECK (expires_at > created_at),
    CONSTRAINT ck_auth_transactions_completed CHECK (status <> 'COMPLETED' OR completed_at IS NOT NULL),
    CONSTRAINT ck_auth_transactions_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_auth_transactions_step_up CHECK (
        purpose <> 'STEP_UP' OR (account_id IS NOT NULL AND session_id IS NOT NULL AND target_action IS NOT NULL)
    ),
    CONSTRAINT ck_auth_transactions_login_mfa CHECK (purpose <> 'LOGIN_MFA' OR account_id IS NOT NULL),
    CONSTRAINT ck_auth_transactions_allowed_methods CHECK (
        JSON_TYPE(allowed_methods) = 'ARRAY' AND JSON_LENGTH(allowed_methods) BETWEEN 1 AND 4
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_step_up_grants'),
                'Create one-time step-up grants.',
                <<<'SQL'
CREATE TABLE account_step_up_grants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assurance_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issued_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_session_action_marker TINYINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'ACTIVE', 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step_up_grants_public_id (public_id),
    UNIQUE KEY uq_step_up_grants_active_action (session_id, action, active_session_action_marker),
    KEY ix_step_up_grants_account_status_expiry (account_id, status, expires_at, id),
    KEY ix_step_up_grants_session_status_expiry (session_id, status, expires_at, id),
    KEY ix_step_up_grants_status_expiry (status, expires_at, id),
    CONSTRAINT fk_step_up_grants_account_session FOREIGN KEY (account_id, session_id)
        REFERENCES user_sessions (account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE',
        'MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY'
    )),
    CONSTRAINT ck_step_up_grants_assurance CHECK (
        assurance_level IN ('PRIMARY','MULTI_FACTOR','PHISHING_RESISTANT')
    ),
    CONSTRAINT ck_step_up_grants_status CHECK (status IN ('ACTIVE','CONSUMED','EXPIRED','REVOKED')),
    CONSTRAINT ck_step_up_grants_version CHECK (version >= 1),
    CONSTRAINT ck_step_up_grants_expiry CHECK (expires_at > issued_at),
    CONSTRAINT ck_step_up_grants_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL),
    CONSTRAINT ck_step_up_grants_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_create_account_mfa_policies'),
                'Create account MFA policies.',
                <<<'SQL'
CREATE TABLE account_mfa_policies (
    account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    preferred_method VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    enabled_at DATETIME(6) NULL,
    disabled_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (account_id),
    KEY ix_mfa_policies_status_updated (status, updated_at),
    CONSTRAINT fk_mfa_policies_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_mfa_policies_status CHECK (status IN ('DISABLED','ENABLED')),
    CONSTRAINT ck_mfa_policies_preferred CHECK (preferred_method IS NULL OR preferred_method IN ('TOTP','PASSKEY')),
    CONSTRAINT ck_mfa_policies_version CHECK (version >= 1),
    CONSTRAINT ck_mfa_policies_enabled CHECK (status <> 'ENABLED' OR enabled_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_step_up_grants'),
                'Drop step-up grants.',
                'DROP TABLE account_step_up_grants'
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_authentication_transactions'),
                'Drop authentication transactions.',
                'DROP TABLE account_authentication_transactions'
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_drop_account_mfa_policies'),
                'Drop MFA policies.',
                'DROP TABLE account_mfa_policies'
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
