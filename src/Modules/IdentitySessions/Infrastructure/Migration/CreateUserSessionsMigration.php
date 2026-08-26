<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Infrastructure\Migration;

use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateUserSessionsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010800_create_user_sessions');
    }

    public function description(): string
    {
        return 'Create secure server-side account sessions.';
    }

    public function dependencies(): array
    {
        return [
            (new CreateUserAccountsMigration())->id(),
            (new CreateUserDevicesMigration())->id(),
        ];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_user_sessions'),
            'Create user sessions.',
            <<<'SQL'
CREATE TABLE user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    login_submission_id BINARY(16) NOT NULL,
    current_token_hash BINARY(32) NOT NULL,
    previous_token_hash BINARY(32) NULL,
    previous_token_expires_at DATETIME(6) NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    issued_at DATETIME(6) NOT NULL,
    authenticated_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    idle_expires_at DATETIME(6) NOT NULL,
    absolute_expires_at DATETIME(6) NOT NULL,
    rotated_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_sessions_public_id (public_id),
    UNIQUE KEY uq_user_sessions_login_submission (login_submission_id),
    UNIQUE KEY uq_user_sessions_current_token (current_token_hash),
    UNIQUE KEY uq_user_sessions_previous_token (previous_token_hash),
    UNIQUE KEY uq_user_sessions_account_id (account_id, id),
    KEY ix_user_sessions_account_status_seen (account_id, status, last_seen_at, id),
    KEY ix_user_sessions_device_status (device_id, status, id),
    KEY ix_user_sessions_status_idle (status, idle_expires_at),
    KEY ix_user_sessions_status_absolute (status, absolute_expires_at),
    KEY ix_user_sessions_status_updated (status, updated_at),
    CONSTRAINT fk_user_sessions_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_user_sessions_account_device FOREIGN KEY (account_id, device_id)
        REFERENCES user_devices (account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_user_sessions_status CHECK (status IN ('ACTIVE','REVOKED','EXPIRED')),
    CONSTRAINT ck_user_sessions_version CHECK (version >= 1),
    CONSTRAINT ck_user_sessions_idle CHECK (idle_expires_at > issued_at),
    CONSTRAINT ck_user_sessions_absolute CHECK (absolute_expires_at > issued_at),
    CONSTRAINT ck_user_sessions_ttl_order CHECK (idle_expires_at <= absolute_expires_at),
    CONSTRAINT ck_user_sessions_last_seen CHECK (last_seen_at >= issued_at),
    CONSTRAINT ck_user_sessions_revoked CHECK (
        status <> 'REVOKED' OR (revoked_at IS NOT NULL AND revoke_reason_code IS NOT NULL)
    ),
    CONSTRAINT ck_user_sessions_previous_pair CHECK (
        (previous_token_hash IS NULL AND previous_token_expires_at IS NULL)
        OR (previous_token_hash IS NOT NULL AND previous_token_expires_at IS NOT NULL)
    ),
    CONSTRAINT ck_user_sessions_revoke_reason CHECK (revoke_reason_code IS NULL OR revoke_reason_code IN (
        'USER_LOGOUT','REMOTE_SESSION_REVOCATION','DEVICE_REVOCATION','SESSION_LIMIT','REAUTHENTICATION',
        'ACCOUNT_NOT_ACTIVE','DEVICE_NOT_ACTIVE','TOKEN_COMPROMISE'
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_user_sessions'),
            'Drop user sessions.',
            'DROP TABLE user_sessions',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
