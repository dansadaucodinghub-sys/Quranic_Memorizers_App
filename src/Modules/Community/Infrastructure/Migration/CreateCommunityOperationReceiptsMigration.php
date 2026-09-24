<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateCommunityOperationReceiptsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922104000_create_community_operation_receipts');
    }
    public function description(): string
    {
        return 'Record tenant-bound, hash-checked idempotent P10 mutation results.';
    }
    public function dependencies(): array
    {
        return [(new ExtendCommunitySecurityVocabularyMigration())->id()];
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
        return [new SqlMigrationStep(new MigrationStepId('001_receipts'), 'Create P10 mutation receipts.', <<<'SQL'
CREATE TABLE community_operation_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, submission_public_id BINARY(16) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 operation_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_fingerprint BINARY(32) NOT NULL, result_public_id BINARY(16) NULL,
 result_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
 result_version INT UNSIGNED NULL, created_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_receipt_submission(submission_public_id),
 KEY ix_p10_receipt_actor(workspace_id,actor_account_id,created_at,id),
 CONSTRAINT fk_p10_receipt_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_receipt_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_receipt_complete CHECK((completed_at IS NULL AND result_public_id IS NULL AND result_status IS NULL AND result_version IS NULL) OR (completed_at IS NOT NULL AND result_public_id IS NOT NULL AND result_status IS NOT NULL AND result_version>=1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL)];
    }
}
