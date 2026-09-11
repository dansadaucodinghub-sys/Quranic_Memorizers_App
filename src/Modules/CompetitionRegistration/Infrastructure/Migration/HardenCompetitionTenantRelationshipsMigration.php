<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionRegistration\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Adds the edition/category composite relationships as a forward-only hardening migration. */
final readonly class HardenCompetitionTenantRelationshipsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911130300_harden_competition_tenant_relationships');
    }
    public function description(): string
    {
        return 'Enforce category membership of edition across windows, registrations, and rosters.';
    }
    public function dependencies(): array
    {
        return [(new CreateCompetitionRegistrationMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_category_edition_candidate_key'), 'Add a composite category edition candidate key.', 'ALTER TABLE competition_categories ADD UNIQUE KEY uq_competition_categories_workspace_edition_id (workspace_id,edition_id,id)'),
            new SqlMigrationStep(new MigrationStepId('002_window_category_edition_fk'), 'Prevent registration window category/edition mismatch.', 'ALTER TABLE competition_registration_windows ADD CONSTRAINT fk_competition_windows_category_edition FOREIGN KEY (workspace_id,edition_id,category_id) REFERENCES competition_categories (workspace_id,edition_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT'),
            new SqlMigrationStep(new MigrationStepId('003_registration_category_edition_fk'), 'Prevent registration category/edition mismatch.', 'ALTER TABLE competition_registrations ADD CONSTRAINT fk_competition_registrations_category_edition FOREIGN KEY (workspace_id,edition_id,category_id) REFERENCES competition_categories (workspace_id,edition_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT'),
            new SqlMigrationStep(new MigrationStepId('004_roster_category_edition_fk'), 'Prevent roster category/edition mismatch.', 'ALTER TABLE competition_rosters ADD CONSTRAINT fk_competition_rosters_category_edition FOREIGN KEY (workspace_id,edition_id,category_id) REFERENCES competition_categories (workspace_id,edition_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT'),
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
