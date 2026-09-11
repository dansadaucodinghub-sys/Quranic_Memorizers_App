<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CompleteQuranReleaseManifestGovernanceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260910110000_complete_quran_release_manifest_governance');
    }

    public function description(): string
    {
        return 'Complete Qur’an release manifest persistence and source-reference indexes.';
    }

    public function dependencies(): array
    {
        return [(new CreateQuranReleaseOperationIdempotencyMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_source_indexes'),
                'Add source-registry lookup indexes required by release governance.',
                'ALTER TABLE quran_reference_sources ADD KEY ix_quran_sources_status_role_code (status, content_role, source_code), ADD KEY ix_quran_sources_authority_version (authority_name, source_version)',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_metadata_reference'),
                'Correct the Tanzil metadata source to its official metadata reference.',
                "UPDATE quran_reference_sources SET source_reference = 'https://tanzil.net/docs/quran_metadata', version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE source_code = 'TANZIL_QURAN_METADATA_1_0' AND source_reference = 'https://tanzil.net/download/'",
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_release_manifests'),
                'Create immutable deterministic Qur’an release manifests.',
                <<<'SQL'
CREATE TABLE quran_release_manifests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 release_id BIGINT UNSIGNED NOT NULL,
 schema_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 canonical_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL,
 sha256 BINARY(32) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_quran_release_manifests_public_id (public_id),
 UNIQUE KEY uq_quran_release_manifests_release (release_id),
 CONSTRAINT fk_quran_release_manifests_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_quran_release_manifests_json CHECK (JSON_VALID(canonical_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_manifests_no_update'),
                'Prevent mutation of deterministic release manifests.',
                "CREATE TRIGGER trg_quran_release_manifests_no_update BEFORE UPDATE ON quran_release_manifests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release manifests are immutable'",
            ),
            new SqlMigrationStep(
                new MigrationStepId('005_manifests_no_delete'),
                'Prevent deletion of deterministic release manifests.',
                "CREATE TRIGGER trg_quran_release_manifests_no_delete BEFORE DELETE ON quran_release_manifests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quran release manifests are immutable'",
            ),
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
