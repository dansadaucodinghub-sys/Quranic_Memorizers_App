<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** P10 owns safe status notices; the broader notification center remains P12. */
final readonly class CreateCommunityNotificationIntentMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922114000_create_community_notification_intents');
    }

    public function description(): string
    {
        return 'Create transactional community status notification intents with bounded delivery state.';
    }

    public function dependencies(): array
    {
        return [(new HardenCommunityModerationAppealTenantKeyMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_intents'), 'Create deduplicated safe notification intents.', <<<'SQL'
CREATE TABLE community_notification_intents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 recipient_account_id BIGINT UNSIGNED NOT NULL, type_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_public_id BINARY(16) NOT NULL, subject_version INT UNSIGNED NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 safe_state_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, locale VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'en',
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, next_attempt_at DATETIME(6) NOT NULL,
 lease_owner BINARY(16) NULL, lease_expires_at DATETIME(6) NULL, delivered_at DATETIME(6) NULL,
 last_error_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_notice_public(public_id),
 UNIQUE KEY uq_p10_notice_dedupe(recipient_account_id,type_code,subject_public_id,subject_version),
 KEY ix_p10_notice_due(status_code,next_attempt_at,id),
 CONSTRAINT fk_p10_notice_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_notice_recipient FOREIGN KEY(recipient_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_notice CHECK(type_code IN ('CLIP_REVIEW_REQUESTED','CLIP_PUBLISHED','CLIP_HIDDEN','CLIP_REMOVED','CLIP_RESTORED','REPORT_RECEIVED','MODERATION_DECISION','APPEAL_RECEIVED','APPEAL_DECISION') AND status_code IN ('PENDING','LEASED','RETRY','DELIVERED','DEAD_LETTER') AND locale IN ('en','ar') AND subject_version>=1 AND attempt_count<=10 AND ((lease_owner IS NULL AND lease_expires_at IS NULL) OR (lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_events'), 'Preserve append-only notification delivery history.', <<<'SQL'
CREATE TABLE community_notification_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, intent_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attempt_count SMALLINT UNSIGNED NOT NULL,
 safe_error_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_notice_event_public(public_id), KEY ix_p10_notice_event_intent(intent_id,id),
 CONSTRAINT fk_p10_notice_event_intent FOREIGN KEY(intent_id) REFERENCES community_notification_intents(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_notice_event CHECK(event_code IN ('CREATED','CLAIMED','DELIVERED','RETRY_SCHEDULED','DEAD_LETTERED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_event_update'), 'Reject notification event changes.', <<<'SQL'
CREATE TRIGGER trg_p10_notice_event_no_update BEFORE UPDATE ON community_notification_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Community notification events are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('004_event_delete'), 'Reject notification event deletion.', <<<'SQL'
CREATE TRIGGER trg_p10_notice_event_no_delete BEFORE DELETE ON community_notification_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Community notification events are immutable'
SQL),
        ];
    }
}
