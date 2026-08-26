<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Infrastructure\Migration;

use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateUserDevicesMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010700_create_user_devices');
    }

    public function description(): string
    {
        return 'Create account-owned, non-authenticating device records.';
    }

    public function dependencies(): array
    {
        return [(new CreateUserAccountsMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_user_devices'),
            'Create user devices.',
            <<<'SQL'
CREATE TABLE user_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    token_hash BINARY(32) NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_devices_public_id (public_id),
    UNIQUE KEY uq_user_devices_account_token (account_id, token_hash),
    UNIQUE KEY uq_user_devices_account_id (account_id, id),
    KEY ix_user_devices_account_status_seen (account_id, status, last_seen_at, id),
    KEY ix_user_devices_status_updated (status, updated_at),
    CONSTRAINT fk_user_devices_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_user_devices_status CHECK (status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_user_devices_version CHECK (version >= 1),
    CONSTRAINT ck_user_devices_revoked CHECK (status <> 'REVOKED' OR revoked_at IS NOT NULL),
    CONSTRAINT ck_user_devices_last_seen CHECK (last_seen_at >= created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_user_devices'),
            'Drop user devices.',
            'DROP TABLE user_devices',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
