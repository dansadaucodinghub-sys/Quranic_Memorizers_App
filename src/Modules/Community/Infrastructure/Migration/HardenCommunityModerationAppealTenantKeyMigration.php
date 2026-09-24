<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only correction: make appeal-to-decision ownership database-enforced. */
final readonly class HardenCommunityModerationAppealTenantKeyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922113500_harden_community_moderation_appeal_tenant_key');
    }

    public function description(): string
    {
        return 'Enforce tenant ownership between community moderation appeals and decisions.';
    }

    public function dependencies(): array
    {
        return [(new CreateCommunityModerationAppealsMigration())->id()];
    }

    public function reversible(): bool
    {
        return false;
    }

    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_decision_tenant_key'),
                'Expose the immutable moderation decision tenant key.',
                'ALTER TABLE community_moderation_decisions '
                . 'ADD UNIQUE KEY uq_p10_decision_workspace_id(workspace_id,id)'
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_unscoped_decision_fk'),
                'Remove the unscoped appeal decision foreign key.',
                'ALTER TABLE community_moderation_appeals '
                . 'DROP FOREIGN KEY fk_p10_appeal_decision'
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_add_tenant_decision_fk'),
                'Add the tenant-bound appeal decision foreign key.',
                'ALTER TABLE community_moderation_appeals '
                . 'ADD CONSTRAINT fk_p10_appeal_decision FOREIGN KEY(workspace_id,decision_id) '
                . 'REFERENCES community_moderation_decisions(workspace_id,id) '
                . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
            ),
        ];
    }
}
