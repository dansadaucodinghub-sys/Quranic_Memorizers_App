<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateCommunitySocialOperationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922105000_create_community_social_operations');
    }
    public function description(): string
    {
        return 'Add account-global idempotency and immutable social interaction history.';
    }
    public function dependencies(): array
    {
        return [(new CreateCommunityOperationReceiptsMigration())->id()];
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
        return [
            new SqlMigrationStep(new MigrationStepId('001_global_receipts'), 'Create cross-workspace account interaction receipts.', <<<'SQL'
CREATE TABLE community_global_operation_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, submission_public_id BINARY(16) NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_fingerprint BINARY(32) NOT NULL, result_public_id BINARY(16) NULL,
 result_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
 result_version INT UNSIGNED NULL, created_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_global_receipt_submission(submission_public_id),
 KEY ix_p10_global_receipt_actor(actor_account_id,created_at,id),
 CONSTRAINT fk_p10_global_receipt_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_global_receipt_complete CHECK((completed_at IS NULL AND result_public_id IS NULL AND result_status IS NULL AND result_version IS NULL) OR (completed_at IS NOT NULL AND result_public_id IS NOT NULL AND result_status IS NOT NULL AND result_version>=1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_social_events'), 'Preserve account-scoped social relationship transitions.', <<<'SQL'
CREATE TABLE community_social_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL, target_account_id BIGINT UNSIGNED NOT NULL,
 profile_id BIGINT UNSIGNED NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 relationship_version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_social_event_public(public_id),
 KEY ix_p10_social_event_actor(actor_account_id,created_at,id),
 KEY ix_p10_social_event_target(target_account_id,created_at,id),
 CONSTRAINT fk_p10_social_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_social_event_target FOREIGN KEY(target_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_social_event_profile FOREIGN KEY(profile_id) REFERENCES community_profiles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_social_event CHECK(event_code IN ('FOLLOW_REQUESTED','FOLLOWED','FOLLOW_ACCEPTED','FOLLOW_DECLINED','UNFOLLOWED','FOLLOWER_REVOKED','BLOCKED','UNBLOCKED','MUTED','UNMUTED') AND relationship_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_no_update'), 'Protect social event history.', "CREATE TRIGGER trg_p10_social_event_no_update BEFORE UPDATE ON community_social_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 social events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('004_no_delete'), 'Protect social event history.', "CREATE TRIGGER trg_p10_social_event_no_delete BEFORE DELETE ON community_social_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 social events are append-only'"),
        ];
    }
}
