<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Infrastructure\Migration;

use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateUserAccountsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010200_create_user_accounts');
    }

    public function description(): string
    {
        return 'Create global user accounts independently from future Person profiles.';
    }

    public function dependencies(): array
    {
        return [(new CreateWorkspacesMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_user_accounts'),
            'Create global user accounts.',
            <<<'SQL'
CREATE TABLE user_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    preferred_locale VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    preferred_time_zone VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_accounts_public_id (public_id),
    KEY ix_user_accounts_status_created (account_status, created_at, id),
    CONSTRAINT ck_user_accounts_status CHECK (
        account_status IN ('PENDING_VERIFICATION','ACTIVE','SUSPENDED','CLOSED')
    ),
    CONSTRAINT ck_user_accounts_locale CHECK (preferred_locale IN ('en','ar')),
    CONSTRAINT ck_user_accounts_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_user_accounts'),
            'Drop global user accounts.',
            'DROP TABLE user_accounts',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
