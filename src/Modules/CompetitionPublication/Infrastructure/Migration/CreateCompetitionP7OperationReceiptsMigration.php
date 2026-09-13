<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Infrastructure\Migration;

use Qmdb\Modules\CompetitionLive\Infrastructure\Migration\CreateCompetitionP7OutboxMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Shared P7 replay ledger for publication and appeal mutations.
 *
 * This is deliberately a new forward migration: the live-operation ledger is
 * constrained to live aggregate semantics and must not be repurposed after it
 * has been applied.
 */
final readonly class CreateCompetitionP7OperationReceiptsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260913110000_create_competition_p7_operation_receipts');
    }

    public function description(): string
    {
        return 'Create replay-safe P7 publication and appeal operation receipts.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionP7OutboxMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_p7_operation_receipts'),
                'Create immutable P7 publication and appeal operation receipts.',
                "CREATE TABLE competition_p7_operations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, submission_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_fingerprint BINARY(32) NOT NULL, aggregate_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_public_id BINARY(16) NOT NULL, result_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version_after INT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_operation_public(public_id), UNIQUE KEY uq_p7_operation_submission(submission_id), KEY ix_p7_operation_workspace(workspace_id,occurred_at,id), CONSTRAINT fk_p7_operation_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_operation CHECK(aggregate_kind IN ('RESULT_PUBLICATION','APPEAL_ADJUDICATION') AND version_after>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
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
