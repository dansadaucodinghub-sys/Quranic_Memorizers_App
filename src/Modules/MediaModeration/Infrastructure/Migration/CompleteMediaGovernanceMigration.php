<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Infrastructure\Migration;

use Qmdb\Modules\MediaCatalog\Infrastructure\Migration\CreateMediaOperationReceiptMigration;
use Qmdb\Shared\Schema\Migration\{Migration, MigrationId, MigrationStepId, SqlMigrationStep};

final readonly class CompleteMediaGovernanceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260917120000_complete_media_governance');
    }
    public function description(): string
    {
        return 'Persist immutable tenant-bound media governance operations.';
    }
    public function dependencies(): array
    {
        return [(new CreateMediaOperationReceiptMigration())->id()];
    }
    public function reversible(): bool
    {
        return true;
    }
    public function down(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_drop_operations'), 'Drop media governance operations in governed test rollback.', 'DROP TABLE media_governance_operations')];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_operations'), 'Create media governance receipts.', <<<'SQL'
CREATE TABLE media_governance_operations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 submission_id BINARY(16) NOT NULL,
 request_fingerprint BINARY(32) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL,
 asset_public_id BINARY(16) NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL,
 action_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result_version INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_media_governance_submission (submission_id),
 KEY ix_media_governance_workspace_asset (workspace_id,asset_id,created_at,id),
 CONSTRAINT fk_media_governance_asset FOREIGN KEY (workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_media_governance_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_media_governance_action CHECK (action_code IN ('approve','reject','withdraw-consent','hold','release-hold','remove','archive')),
 CONSTRAINT ck_media_governance_result CHECK (result_version > 1 AND result_status IN ('STAGING','QUARANTINED','SCANNING','PROCESSING','PENDING_MODERATION','APPROVED','PUBLISHED','WITHDRAWN','ARCHIVED','REJECTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_receipt_no_update'), 'Prevent governance receipt replacement.', "CREATE TRIGGER trg_media_governance_no_update BEFORE UPDATE ON media_governance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Media governance operations are append-only'"),
            new SqlMigrationStep(new MigrationStepId('003_receipt_no_delete'), 'Prevent governance receipt deletion.', "CREATE TRIGGER trg_media_governance_no_delete BEFORE DELETE ON media_governance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Media governance operations are append-only'"),
        ];
    }
}
