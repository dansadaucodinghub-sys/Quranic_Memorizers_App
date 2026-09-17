<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Persisted, request-bound P9 mutation replay evidence. */
final readonly class CreateMediaOperationReceiptMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260915113000_create_media_operation_receipts'); }
    public function description(): string { return 'Create P9 media operation receipts for transaction-bound idempotency.'; }
    public function dependencies(): array { return [(new ExtendMediaEvidenceRuntimeMigration())->id()]; }
    public function reversible(): bool { return false; }
    public function down(): array { return []; }
    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_receipts'), 'Create P9 media upload receipts.', <<<'SQL'
CREATE TABLE media_operation_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, submission_public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 operation_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_fingerprint BINARY(32) NOT NULL, asset_public_id BINARY(16) NULL,
 result_status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL, result_version INT UNSIGNED NULL, completed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p9_media_receipt_submission(submission_public_id), KEY ix_p9_media_receipt_workspace(workspace_id,created_at,id),
 CONSTRAINT fk_p9_media_receipt_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p9_media_receipt_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p9_media_receipt CHECK(operation_code='UPLOAD' AND ((completed_at IS NULL AND asset_public_id IS NULL AND result_status IS NULL AND result_version IS NULL) OR (completed_at IS NOT NULL AND asset_public_id IS NOT NULL AND result_status='QUARANTINED' AND result_version>=2)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL)];
    }
}
