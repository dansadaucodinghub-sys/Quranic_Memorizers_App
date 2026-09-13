<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Migration;

use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Migration\CreateCompetitionAppealAdjudicationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only P7 delivery ledger; no historical P7 table is changed. */
final readonly class CreateCompetitionP7OutboxMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260913100000_create_competition_p7_outbox');
    }

    public function description(): string
    {
        return 'Create P7 live-event projection outbox with bounded retries and dead-letter evidence.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionAppealAdjudicationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_p7_outbox'), 'Create transactionally enqueued P7 projection messages.', "CREATE TABLE competition_p7_outbox_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, live_session_id BIGINT UNSIGNED NULL, live_event_public_id BINARY(16) NULL, message_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, payload_canonical_json JSON NOT NULL, payload_sha256 BINARY(32) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING', attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, available_at DATETIME(6) NOT NULL, lease_owner VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL, lease_expires_at DATETIME(6) NULL, delivered_at DATETIME(6) NULL, dead_lettered_at DATETIME(6) NULL, last_error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_outbox_public(public_id), UNIQUE KEY uq_p7_outbox_message(workspace_id,live_event_public_id,message_type), KEY ix_p7_outbox_due(status,available_at,id), KEY ix_p7_outbox_lease(status,lease_expires_at,id), CONSTRAINT fk_p7_outbox_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_outbox_session FOREIGN KEY(workspace_id,live_session_id) REFERENCES competition_live_sessions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_outbox CHECK(message_type IN ('LIVE_EVENT_PROJECT','RESULT_PUBLICATION_PROJECT','APPEAL_DECISION_PROJECT') AND status IN ('PENDING','CLAIMED','DELIVERED','DEAD_LETTER') AND attempts<=25 AND ((status='CLAIMED' AND lease_owner IS NOT NULL AND lease_expires_at IS NOT NULL) OR (status<>'CLAIMED' AND lease_owner IS NULL AND lease_expires_at IS NULL)) AND ((status='DELIVERED' AND delivered_at IS NOT NULL) OR status<>'DELIVERED') AND ((status='DEAD_LETTER' AND dead_lettered_at IS NOT NULL) OR status<>'DEAD_LETTER'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
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
