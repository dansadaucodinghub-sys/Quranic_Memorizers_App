<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Infrastructure\Migration;

use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateWorkspaceMembershipsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010400_create_workspace_memberships');
    }

    public function description(): string
    {
        return 'Create tenant-owned workspace memberships without role or permission semantics.';
    }

    public function dependencies(): array
    {
        return [(new CreateAccountSecurityFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_create_workspace_memberships'),
            'Create tenant-owned workspace memberships.',
            <<<'SQL'
CREATE TABLE workspace_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_account_id BIGINT UNSIGNED NOT NULL,
    status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workspace_memberships_public_id (public_id),
    UNIQUE KEY uq_workspace_memberships_workspace_account (workspace_id, user_account_id),
    UNIQUE KEY uq_workspace_memberships_workspace_id_id (workspace_id, id),
    KEY ix_workspace_memberships_account (user_account_id, id),
    KEY ix_workspace_memberships_workspace_status (workspace_id, status_code, created_at, id),
    CONSTRAINT fk_workspace_memberships_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_workspace_memberships_account FOREIGN KEY (user_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_workspace_memberships_status CHECK (
        status_code IN ('INVITED','ACTIVE','SUSPENDED','REVOKED')
    ),
    CONSTRAINT ck_workspace_memberships_version CHECK (version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
        )];
    }

    public function down(): array
    {
        return [new SqlMigrationStep(
            new MigrationStepId('001_drop_workspace_memberships'),
            'Drop tenant-owned workspace memberships.',
            'DROP TABLE workspace_memberships',
        )];
    }

    public function reversible(): bool
    {
        return true;
    }
}
