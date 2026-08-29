<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Infrastructure\Migration;

use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreateWorkspaceRoleAssignmentFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class AddSessionBoundTenantContextMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011900_add_session_bound_tenant_context');
    }

    public function description(): string
    {
        return 'Bind one explicit, versioned workspace membership selection to each authenticated session.';
    }

    public function dependencies(): array
    {
        return [(new CreateWorkspaceRoleAssignmentFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_add_membership_tenant_candidate_key'),
                'Add the account-bound membership candidate key used by session tenant context.',
                'ALTER TABLE workspace_memberships ADD UNIQUE KEY '
                    . 'uq_workspace_memberships_workspace_account_id (workspace_id, user_account_id, id)',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_add_session_tenant_context'),
                'Add selected workspace state, optimistic versioning, indexes and composite tenant integrity.',
                <<<'SQL'
ALTER TABLE user_sessions
    ADD COLUMN selected_workspace_id BIGINT UNSIGNED NULL AFTER device_id,
    ADD COLUMN selected_membership_id BIGINT UNSIGNED NULL AFTER selected_workspace_id,
    ADD COLUMN tenant_context_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER selected_membership_id,
    ADD COLUMN tenant_context_selected_at DATETIME(6) NULL AFTER tenant_context_version,
    ADD KEY ix_user_sessions_account_workspace_status (account_id, selected_workspace_id, status, id),
    ADD KEY ix_user_sessions_selected_membership_status (selected_workspace_id, selected_membership_id, status),
    ADD CONSTRAINT fk_user_sessions_selected_workspace_membership_account
        FOREIGN KEY (selected_workspace_id, account_id, selected_membership_id)
        REFERENCES workspace_memberships (workspace_id, user_account_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ck_user_sessions_tenant_context_version
        CHECK (tenant_context_version BETWEEN 1 AND 9007199254740991),
    ADD CONSTRAINT ck_user_sessions_tenant_context_complete CHECK (
        (selected_workspace_id IS NULL AND selected_membership_id IS NULL AND tenant_context_selected_at IS NULL)
        OR
        (selected_workspace_id IS NOT NULL AND selected_membership_id IS NOT NULL
            AND tenant_context_selected_at IS NOT NULL)
    )
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_session_tenant_context'),
                'Drop session tenant context constraints, indexes and columns.',
                <<<'SQL'
ALTER TABLE user_sessions
    DROP FOREIGN KEY fk_user_sessions_selected_workspace_membership_account,
    DROP CHECK ck_user_sessions_tenant_context_version,
    DROP CHECK ck_user_sessions_tenant_context_complete,
    DROP INDEX ix_user_sessions_account_workspace_status,
    DROP INDEX ix_user_sessions_selected_membership_status,
    DROP COLUMN tenant_context_selected_at,
    DROP COLUMN tenant_context_version,
    DROP COLUMN selected_membership_id,
    DROP COLUMN selected_workspace_id
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_membership_tenant_candidate_key'),
                'Drop the account-bound membership candidate key.',
                'ALTER TABLE workspace_memberships DROP INDEX uq_workspace_memberships_workspace_account_id',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
