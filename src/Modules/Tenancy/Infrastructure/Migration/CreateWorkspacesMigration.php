<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateWorkspacesMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010100_create_workspaces');
    }

    public function description(): string
    {
        return 'Create the globally governed workspace foundation.';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_workspaces'),
            'Create globally governed workspaces.',
            <<<'SQL'
CREATE TABLE workspaces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    workspace_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(191) NOT NULL,
    status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workspaces_public_id (public_id),
    UNIQUE KEY uq_workspaces_code (workspace_code),
    KEY ix_workspaces_status_created (status_code, created_at, id),
    CONSTRAINT ck_workspaces_status CHECK (status_code IN ('ACTIVE','SUSPENDED','CLOSED')),
    CONSTRAINT ck_workspaces_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_workspaces'),
            'Drop globally governed workspaces.',
            'DROP TABLE workspaces',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
