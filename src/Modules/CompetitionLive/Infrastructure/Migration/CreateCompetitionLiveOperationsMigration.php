<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Migration;

use Qmdb\Modules\CompetitionResults\Infrastructure\Migration\AddCompetitionP6ResultInputUniquenessMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * P7's authoritative live-operation ledger.  Projections are derived from
 * this data; they are deliberately never used as an operational authority.
 */
final readonly class CreateCompetitionLiveOperationsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912140000_create_competition_live_operations');
    }

    public function description(): string
    {
        return 'Create P7 workspace-safe live sessions, states, and tamper-evident events.';
    }

    public function dependencies(): array
    {
        return [(new AddCompetitionP6ResultInputUniquenessMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_live_sessions'),
                'Create authoritative live competition sessions.',
                "CREATE TABLE competition_live_sessions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, edition_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, round_id BIGINT UNSIGNED NOT NULL, venue_id BIGINT UNSIGNED NULL, session_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PLANNED', public_visibility VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PRIVATE', transport_policy VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SNAPSHOT_ONLY', head_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, projection_version INT UNSIGNED NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, created_by_account_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, opened_at DATETIME(6) NULL, paused_at DATETIME(6) NULL, recovery_started_at DATETIME(6) NULL, recovered_at DATETIME(6) NULL, closed_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_live_session_public(public_id), UNIQUE KEY uq_p7_live_session_code(workspace_id,round_id,session_code), UNIQUE KEY uq_p7_live_session_workspace_id(workspace_id,id), KEY ix_p7_live_session_status(workspace_id,status,updated_at,id), KEY ix_p7_live_session_round(workspace_id,round_id,status,id), KEY ix_p7_live_session_public(public_visibility,status,opened_at,id), CONSTRAINT fk_p7_live_session_edition FOREIGN KEY(workspace_id,edition_id) REFERENCES competition_editions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_session_category FOREIGN KEY(workspace_id,category_id) REFERENCES competition_categories(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_session_round FOREIGN KEY(workspace_id,round_id) REFERENCES competition_rounds(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_session_venue FOREIGN KEY(workspace_id,venue_id) REFERENCES competition_venues(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_session_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_live_session CHECK(status IN ('PLANNED','OPEN','PAUSED','RECOVERING','CLOSED','CANCELLED') AND public_visibility IN ('PRIVATE','PUBLIC') AND transport_policy IN ('SNAPSHOT_ONLY','SSE_WITH_POLLING_FALLBACK') AND head_sequence>=0 AND projection_version>=1 AND version>=1 AND (status <> 'CLOSED' OR closed_at IS NOT NULL) AND (status <> 'CANCELLED' OR cancelled_at IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_live_participant_states'),
                'Create controlled operational states for existing round participants.',
                "CREATE TABLE competition_live_participant_states (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, live_session_id BIGINT UNSIGNED NOT NULL, round_participant_id BIGINT UNSIGNED NOT NULL, current_state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SCHEDULED', call_sequence INT UNSIGNED NOT NULL, state_version INT UNSIGNED NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, checked_in_at DATETIME(6) NULL, called_at DATETIME(6) NULL, performance_started_at DATETIME(6) NULL, interrupted_at DATETIME(6) NULL, completed_at DATETIME(6) NULL, absent_at DATETIME(6) NULL, withdrawn_at DATETIME(6) NULL, disqualified_at DATETIME(6) NULL, cancelled_at DATETIME(6) NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_live_participant_public(public_id), UNIQUE KEY uq_p7_live_participant_member(workspace_id,live_session_id,round_participant_id), UNIQUE KEY uq_p7_live_participant_call(workspace_id,live_session_id,call_sequence), UNIQUE KEY uq_p7_live_participant_workspace_id(workspace_id,id), KEY ix_p7_live_participant_state(workspace_id,live_session_id,current_state,call_sequence,id), KEY ix_p7_live_participant_round(workspace_id,round_participant_id,current_state,id), CONSTRAINT fk_p7_live_participant_session FOREIGN KEY(workspace_id,live_session_id) REFERENCES competition_live_sessions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_participant_round_participant FOREIGN KEY(workspace_id,round_participant_id) REFERENCES competition_round_participants(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_live_participant CHECK(current_state IN ('SCHEDULED','CHECKED_IN','CALLED','READY','PERFORMING','INTERRUPTED','COMPLETED','ABSENT','WITHDRAWN','DISQUALIFIED','CANCELLED') AND call_sequence>=1 AND state_version>=1 AND version>=1 AND (current_state <> 'COMPLETED' OR completed_at IS NOT NULL) AND (current_state <> 'ABSENT' OR absent_at IS NOT NULL) AND (current_state <> 'WITHDRAWN' OR withdrawn_at IS NOT NULL) AND (current_state <> 'DISQUALIFIED' OR disqualified_at IS NOT NULL) AND (current_state <> 'CANCELLED' OR cancelled_at IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_live_events'),
                'Create sequenced, hash-chained live events.',
                "CREATE TABLE competition_live_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, live_session_id BIGINT UNSIGNED NOT NULL, sequence_number BIGINT UNSIGNED NOT NULL, event_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, visibility VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, payload_schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1, payload_canonical_json JSON NOT NULL, payload_sha256 BINARY(32) NOT NULL, previous_event_sha256 BINARY(32) NULL, event_sha256 BINARY(32) NOT NULL, actor_type VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, idempotency_fingerprint BINARY(32) NOT NULL, correlation_id BINARY(16) NOT NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_live_event_public(public_id), UNIQUE KEY uq_p7_live_event_sequence(workspace_id,live_session_id,sequence_number), UNIQUE KEY uq_p7_live_event_idempotency(workspace_id,live_session_id,idempotency_fingerprint), KEY ix_p7_live_event_stream(workspace_id,live_session_id,sequence_number,id), CONSTRAINT fk_p7_live_event_session FOREIGN KEY(workspace_id,live_session_id) REFERENCES competition_live_sessions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_live_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_live_event CHECK(sequence_number>=1 AND payload_schema_version>=1 AND visibility IN ('PUBLIC_SAFE','WORKSPACE_PRIVATE','SYSTEM_PRIVATE') AND actor_type IN ('ACCOUNT','SYSTEM') AND ((actor_type='ACCOUNT' AND actor_account_id IS NOT NULL) OR (actor_type='SYSTEM' AND actor_account_id IS NULL)) AND event_type IN ('SESSION_OPENED','SESSION_PAUSED','SESSION_RESUMED','SESSION_RECOVERY_STARTED','SESSION_RECOVERED','SESSION_CLOSED','SESSION_CANCELLED','PARTICIPANT_CHECKED_IN','PARTICIPANT_CALLED','PARTICIPANT_READY','PERFORMANCE_STARTED','PERFORMANCE_INTERRUPTED','PERFORMANCE_RESUMED','PERFORMANCE_COMPLETED','PARTICIPANT_ABSENT','PARTICIPANT_WITHDRAWN','PARTICIPANT_DISQUALIFIED','JUDGING_PROGRESS_UPDATED','PROVISIONAL_RESULT_AVAILABLE','RESULT_PUBLICATION_HELD','RESULT_PUBLICATION_RELEASED','FINAL_RESULT_PUBLISHED','CORRECTED_RESULT_PUBLISHED','APPEAL_WINDOW_OPENED','APPEAL_WINDOW_CLOSED'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(new MigrationStepId('004_live_events_no_update'), 'Prevent modification of authoritative live events.', "CREATE TRIGGER trg_p7_live_events_no_update BEFORE UPDATE ON competition_live_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Live events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('005_live_events_no_delete'), 'Prevent deletion of authoritative live events.', "CREATE TRIGGER trg_p7_live_events_no_delete BEFORE DELETE ON competition_live_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Live events are append-only'"),
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
