<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateAccountSecurityFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010300_create_account_security_foundation');
    }

    public function description(): string
    {
        return 'Create encrypted contact, password credential, and account status event records.';
    }

    public function dependencies(): array
    {
        return [(new CreateUserAccountsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_email_addresses'),
                'Create encrypted account email addresses.',
                <<<'SQL'
CREATE TABLE account_email_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    user_account_id BIGINT UNSIGNED NOT NULL,
    email_ciphertext VARBINARY(2048) NOT NULL,
    encryption_key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    lookup_hash BINARY(32) NOT NULL,
    status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active_lookup_hash BINARY(32)
        GENERATED ALWAYS AS (IF(status_code IN ('UNVERIFIED','VERIFIED'), lookup_hash, NULL)) STORED,
    verified_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_email_addresses_public_id (public_id),
    UNIQUE KEY uq_account_email_addresses_active_lookup (active_lookup_hash),
    KEY ix_account_email_addresses_account_created (user_account_id, created_at, id),
    CONSTRAINT fk_account_email_addresses_account FOREIGN KEY (user_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_account_email_addresses_status CHECK (status_code IN ('UNVERIFIED','VERIFIED','REVOKED')),
    CONSTRAINT ck_account_email_addresses_verified CHECK (
        (status_code = 'VERIFIED' AND verified_at IS NOT NULL) OR status_code <> 'VERIFIED'
    ),
    CONSTRAINT ck_account_email_addresses_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_phone_numbers'),
                'Create encrypted account phone numbers.',
                <<<'SQL'
CREATE TABLE account_phone_numbers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    user_account_id BIGINT UNSIGNED NOT NULL,
    phone_ciphertext VARBINARY(2048) NOT NULL,
    encryption_key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    lookup_hash BINARY(32) NOT NULL,
    status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active_lookup_hash BINARY(32)
        GENERATED ALWAYS AS (IF(status_code IN ('UNVERIFIED','VERIFIED'), lookup_hash, NULL)) STORED,
    verified_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_phone_numbers_public_id (public_id),
    UNIQUE KEY uq_account_phone_numbers_active_lookup (active_lookup_hash),
    KEY ix_account_phone_numbers_account_created (user_account_id, created_at, id),
    CONSTRAINT fk_account_phone_numbers_account FOREIGN KEY (user_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_account_phone_numbers_status CHECK (status_code IN ('UNVERIFIED','VERIFIED','REVOKED')),
    CONSTRAINT ck_account_phone_numbers_verified CHECK (
        (status_code = 'VERIFIED' AND verified_at IS NOT NULL) OR status_code <> 'VERIFIED'
    ),
    CONSTRAINT ck_account_phone_numbers_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_create_account_credentials'),
                'Create password credential records.',
                <<<'SQL'
CREATE TABLE account_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    user_account_id BIGINT UNSIGNED NOT NULL,
    credential_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    credential_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    algorithm VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    metadata_version SMALLINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    active_password_account_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            IF(credential_type = 'PASSWORD' AND credential_status = 'ACTIVE', user_account_id, NULL)
        ) STORED,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_credentials_public_id (public_id),
    UNIQUE KEY uq_account_credentials_active_password (active_password_account_id),
    KEY ix_account_credentials_account_created (user_account_id, created_at, id),
    CONSTRAINT fk_account_credentials_account FOREIGN KEY (user_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_account_credentials_type CHECK (credential_type IN ('PASSWORD')),
    CONSTRAINT ck_account_credentials_status CHECK (credential_status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_account_credentials_metadata_version CHECK (metadata_version >= 1),
    CONSTRAINT ck_account_credentials_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_create_account_status_events'),
                'Create append-only account status events.',
                <<<'SQL'
CREATE TABLE account_status_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_account_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    payload_json JSON NULL,
    content_hash BINARY(32) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_account_status_events_account_occurred (user_account_id, occurred_at, id),
    KEY ix_account_status_events_occurred (occurred_at, id),
    CONSTRAINT fk_account_status_events_account FOREIGN KEY (user_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_status_events'),
                'Drop append-only account status events.',
                'DROP TABLE account_status_events',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_credentials'),
                'Drop password credential records.',
                'DROP TABLE account_credentials',
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_drop_account_phone_numbers'),
                'Drop encrypted account phone numbers.',
                'DROP TABLE account_phone_numbers',
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_drop_account_email_addresses'),
                'Drop encrypted account email addresses.',
                'DROP TABLE account_email_addresses',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
