<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Rejects duplicate calculations of the same immutable locked-score input. */
final readonly class AddCompetitionP6ResultInputUniquenessMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912133000_add_competition_p6_result_input_uniqueness');
    }

    public function description(): string
    {
        return 'Prevent duplicate P6 result runs for identical locked-score input.';
    }

    public function dependencies(): array
    {
        return [(new CompleteCompetitionP6RuntimeContractsMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_result_input_unique'), 'Reject duplicate result calculations for an immutable input set.', 'ALTER TABLE competition_result_runs ADD UNIQUE KEY uq_p6_run_input (workspace_id,round_id,input_checksum_sha256)')];
    }

    public function down(): array
    {
        return [];
    }

    public function reversible(): bool
    {
        return false;
    }
}
