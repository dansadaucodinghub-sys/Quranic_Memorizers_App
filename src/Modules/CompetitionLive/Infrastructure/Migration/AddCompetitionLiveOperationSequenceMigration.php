<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only correction: replay receipts must preserve their event sequence. */
final readonly class AddCompetitionLiveOperationSequenceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912142000_add_competition_live_operation_sequence');
    }

    public function description(): string
    {
        return 'Persist the authoritative live-event sequence in P7 operation receipts.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionLiveProjectionsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_sequence_after'), 'Add replay-safe event sequence to live operation receipts.', 'ALTER TABLE competition_live_operations ADD COLUMN sequence_after BIGINT UNSIGNED NOT NULL AFTER version_after, ADD CONSTRAINT ck_p7_live_operation_sequence CHECK(sequence_after>=1)'),
        ];
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
