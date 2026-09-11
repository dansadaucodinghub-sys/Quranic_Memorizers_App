<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class EnableQuranSystemBaselineActorMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911080200_enable_quran_system_baseline_actor');
    }

    public function description(): string
    {
        return 'Allow the closed SYSTEM actor for the authorized Qur’an baseline installer.';
    }

    public function dependencies(): array
    {
        return [(new CreateQuranCanonicalContentMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_release_creator_actor'), 'Add the closed release-creator actor type.', <<<'SQL'
ALTER TABLE quran_reference_releases
 ADD created_by_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACCOUNT' AFTER validation_policy_version,
 MODIFY created_by_account_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT ck_quran_releases_creator_actor CHECK ((created_by_type = 'ACCOUNT' AND created_by_account_id IS NOT NULL) OR (created_by_type = 'SYSTEM' AND created_by_account_id IS NULL))
SQL),
            new SqlMigrationStep(new MigrationStepId('002_validation_actor'), 'Require a valid Account relationship for account validation actors.', "ALTER TABLE quran_release_validations ADD CONSTRAINT ck_quran_validations_account_actor CHECK ((executed_by_type = 'ACCOUNT' AND executed_by_account_id IS NOT NULL) OR (executed_by_type = 'SYSTEM' AND executed_by_account_id IS NULL))"),
            new SqlMigrationStep(new MigrationStepId('003_event_actor'), 'Require a valid Account relationship for account lifecycle actors.', "ALTER TABLE quran_release_events ADD CONSTRAINT ck_quran_events_account_actor CHECK ((actor_type = 'ACCOUNT' AND actor_account_id IS NOT NULL) OR (actor_type = 'SYSTEM' AND actor_account_id IS NULL))"),
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
