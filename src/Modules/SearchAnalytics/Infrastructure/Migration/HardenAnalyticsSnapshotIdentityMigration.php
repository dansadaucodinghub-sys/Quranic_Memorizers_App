<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only correction for NULL-safe snapshot identity and completed-run deletion protection. */
final readonly class HardenAnalyticsSnapshotIdentityMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260925104000_harden_analytics_snapshot_identity');
    }

    public function description(): string
    {
        return 'Make snapshot identities NULL-safe and completed snapshots deletion-immutable.';
    }

    public function dependencies(): array
    {
        return [(new CreateReportingExportRuntimeMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_null_safe_identity'), 'Harden snapshot identity.', <<<'SQL'
ALTER TABLE analytics_snapshot_runs
 DROP INDEX uq_p11_snapshot_identity,
 ADD COLUMN workspace_identity_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(workspace_id,0)) STORED AFTER workspace_id,
 ADD UNIQUE KEY uq_p11_snapshot_identity(metric_definition_id,workspace_identity_id,period_start,period_end,source_watermark)
SQL),
            new SqlMigrationStep(new MigrationStepId('002_completed_delete'), 'Reject completed snapshot deletion.', <<<'SQL'
CREATE TRIGGER trg_p11_snapshot_completed_no_delete BEFORE DELETE ON analytics_snapshot_runs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Analytics snapshot runs are immutable'
SQL),
        ];
    }
}
