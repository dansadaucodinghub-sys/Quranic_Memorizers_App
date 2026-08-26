<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Migration;

use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateIdentityVerificationFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010500_create_identity_verification_foundation');
    }

    public function description(): string
    {
        return 'Create identity idempotency and email-verification challenge records.';
    }

    public function dependencies(): array
    {
        return [(new CreateAccountSecurityFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_identity_idempotency_records'),
                'Create identity idempotency records.',
                <<<'SQL'
CREATE TABLE identity_idempotency_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    operation VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_fingerprint BINARY(32) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_identity_idempotency_public_id (public_id),
    KEY ix_identity_idempotency_operation_created (operation, created_at, id),
    KEY ix_identity_idempotency_completed_operation (completed_at, operation, id),
    CONSTRAINT ck_identity_idempotency_operation CHECK (
        operation IN ('ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND')
    ),
    CONSTRAINT ck_identity_idempotency_completed CHECK (
        completed_at IS NULL OR completed_at >= created_at
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_email_verification_challenges'),
                'Create account email-verification challenges.',
                <<<'SQL'
CREATE TABLE account_email_verification_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_email_address_id BIGINT UNSIGNED NOT NULL,
    token_hash BINARY(32) NOT NULL,
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
    active_email_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'PENDING', account_email_address_id, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_verification_public_id (public_id),
    UNIQUE KEY uq_email_verification_token_hash (token_hash),
    UNIQUE KEY uq_email_verification_active_email (active_email_key),
    KEY ix_email_verification_status_expiry (status, expires_at, id),
    KEY ix_email_verification_email_created (account_email_address_id, created_at, id),
    CONSTRAINT fk_email_verification_email FOREIGN KEY (account_email_address_id)
        REFERENCES account_email_addresses (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_email_verification_status CHECK (status IN ('PENDING','CONSUMED','EXPIRED','REVOKED')),
    CONSTRAINT ck_email_verification_attempts CHECK (attempt_count <= maximum_attempts),
    CONSTRAINT ck_email_verification_maximum CHECK (maximum_attempts >= 1),
    CONSTRAINT ck_email_verification_version CHECK (version >= 1),
    CONSTRAINT ck_email_verification_expiry CHECK (expires_at > created_at),
    CONSTRAINT ck_email_verification_consumed CHECK (status <> 'CONSUMED' OR consumed_at IS NOT NULL),
    CONSTRAINT ck_email_verification_expired CHECK (status <> 'EXPIRED' OR expired_at IS NOT NULL),
    CONSTRAINT ck_email_verification_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_email_verification_challenges'),
                'Drop account email-verification challenges.',
                'DROP TABLE account_email_verification_challenges',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_identity_idempotency_records'),
                'Drop identity idempotency records.',
                'DROP TABLE identity_idempotency_records',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
