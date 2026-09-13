<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Forward-only public projection ledger. Result packages remain immutable and
 * this ledger gives public reads a separately verifiable, versioned authority.
 */
final readonly class CreateCompetitionResultPublicationProjectionMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260913120000_create_competition_result_publication_projections');
    }

    public function description(): string
    {
        return 'Create immutable P7 result-publication public projection snapshots and current pointers.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionP7OperationReceiptsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_projection_snapshots'), 'Create immutable public-safe result projection snapshots.', "CREATE TABLE competition_result_publication_projections (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, publication_id BIGINT UNSIGNED NOT NULL, package_id BIGINT UNSIGNED NOT NULL, projection_version INT UNSIGNED NOT NULL, public_payload_canonical_json JSON NOT NULL, projection_sha256 BINARY(32) NOT NULL, source_result_run_sha256 BINARY(32) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_publication_projection_public(public_id), UNIQUE KEY uq_p7_publication_projection_version(workspace_id,publication_id,projection_version), UNIQUE KEY uq_p7_publication_projection_workspace_id(workspace_id,id), KEY ix_p7_publication_projection_publication(workspace_id,publication_id,projection_version,id), CONSTRAINT fk_p7_publication_projection_publication FOREIGN KEY(workspace_id,publication_id) REFERENCES competition_result_publications(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_publication_projection_package FOREIGN KEY(workspace_id,package_id) REFERENCES competition_result_packages(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_publication_projection CHECK(projection_version>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('002_projection_heads'), 'Create mutable current-projection pointers without mutating snapshots.', "CREATE TABLE competition_result_publication_projection_heads (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, publication_id BIGINT UNSIGNED NOT NULL, current_projection_id BIGINT UNSIGNED NOT NULL, current_projection_sha256 BINARY(32) NOT NULL, cache_generation INT UNSIGNED NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_publication_projection_head(workspace_id,publication_id), CONSTRAINT fk_p7_publication_projection_head_publication FOREIGN KEY(workspace_id,publication_id) REFERENCES competition_result_publications(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_publication_projection_head_snapshot FOREIGN KEY(workspace_id,current_projection_id) REFERENCES competition_result_publication_projections(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_publication_projection_head CHECK(cache_generation>=1 AND version>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('003_projection_no_update'), 'Prevent result projection snapshot mutation.', "CREATE TRIGGER trg_p7_result_projection_no_update BEFORE UPDATE ON competition_result_publication_projections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Result publication projections are immutable'"),
            new SqlMigrationStep(new MigrationStepId('004_projection_no_delete'), 'Prevent result projection snapshot deletion.', "CREATE TRIGGER trg_p7_result_projection_no_delete BEFORE DELETE ON competition_result_publication_projections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Result publication projections are immutable'"),
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
