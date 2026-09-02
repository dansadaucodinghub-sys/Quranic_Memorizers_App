<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePersonCanonicalAliasesMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902050400_create_person_canonical_aliases');
    }

    public function description(): string
    {
        return 'Create immutable retired-Person canonical aliases for consent-gated duplicate resolution.';
    }

    public function dependencies(): array
    {
        return [(new CreatePersonDuplicateCasesMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_person_aliases'), 'Create immutable source-to-canonical Person aliases.', <<<'SQL'
CREATE TABLE people_person_aliases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    source_person_id BIGINT UNSIGNED NOT NULL,
    canonical_person_id BIGINT UNSIGNED NOT NULL,
    duplicate_case_id BIGINT UNSIGNED NOT NULL,
    created_by_account_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_person_aliases_public_id (public_id),
    UNIQUE KEY uq_people_person_aliases_source (source_person_id),
    KEY ix_people_person_aliases_canonical_created (canonical_person_id, created_at, id),
    KEY ix_people_person_aliases_case (duplicate_case_id, id),
    CONSTRAINT fk_people_person_aliases_source FOREIGN KEY (source_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_aliases_canonical FOREIGN KEY (canonical_person_id) REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_aliases_case FOREIGN KEY (duplicate_case_id) REFERENCES people_duplicate_cases (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_person_aliases_created_by FOREIGN KEY (created_by_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_person_aliases_distinct CHECK (source_person_id <> canonical_person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_person_alias_update_trigger'), 'Reject mutation of Person aliases.', "CREATE TRIGGER tr_people_person_aliases_no_update BEFORE UPDATE ON people_person_aliases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Person aliases are immutable'"),
            new SqlMigrationStep(new MigrationStepId('003_person_alias_delete_trigger'), 'Reject deletion of Person aliases.', "CREATE TRIGGER tr_people_person_aliases_no_delete BEFORE DELETE ON people_person_aliases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Person aliases are immutable'"),
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
