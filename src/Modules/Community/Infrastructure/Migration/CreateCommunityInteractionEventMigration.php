<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateCommunityInteractionEventMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922110000_create_community_interaction_events');
    }
    public function description(): string
    {
        return 'Preserve idempotent reaction and bookmark transition evidence.';
    }
    public function dependencies(): array
    {
        return [(new CreateCommunitySocialOperationMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_events'), 'Create immutable Clip interaction transitions.', <<<'SQL'
CREATE TABLE community_interaction_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL,
 workspace_id BIGINT UNSIGNED NOT NULL, clip_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL, interaction_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 interaction_version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_interaction_event_public(public_id),
 UNIQUE KEY uq_p10_interaction_event_version(clip_id,actor_account_id,interaction_kind,interaction_version),
 KEY ix_p10_interaction_event_actor(actor_account_id,created_at,id),
 CONSTRAINT fk_p10_interaction_event_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_interaction_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_interaction_event CHECK(interaction_kind IN ('REACTION','BOOKMARK') AND event_code IN ('ADDED','REMOVED') AND interaction_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_no_update'), 'Protect interaction history.', "CREATE TRIGGER trg_p10_interaction_event_no_update BEFORE UPDATE ON community_interaction_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 interaction events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('003_no_delete'), 'Protect interaction history.', "CREATE TRIGGER trg_p10_interaction_event_no_delete BEFORE DELETE ON community_interaction_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 interaction events are append-only'"),
        ];
    }
}
