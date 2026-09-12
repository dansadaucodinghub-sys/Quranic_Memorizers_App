<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Infrastructure\Migration;

use Qmdb\Modules\CompetitionRegistration\Infrastructure\Migration\HardenCompetitionTenantRelationshipsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Adds the P6-required candidate key without altering an applied P5 migration. */
final readonly class AddCompetitionRosterEntryTenantCandidateKeyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911139900_add_competition_roster_entry_tenant_key');
    }
    public function description(): string
    {
        return 'Add a roster-entry tenant candidate key for P6 participant isolation.';
    }
    public function dependencies(): array
    {
        return [(new HardenCompetitionTenantRelationshipsMigration())->id()];
    }
    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_roster_entry_tenant_key'), 'Add roster entry workspace candidate key.', 'ALTER TABLE competition_roster_entries ADD UNIQUE KEY uq_competition_roster_entries_workspace_id (workspace_id,id)')];
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
