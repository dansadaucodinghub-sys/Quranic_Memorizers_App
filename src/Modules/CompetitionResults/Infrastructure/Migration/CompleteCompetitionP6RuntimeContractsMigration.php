<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Adds the operational ledgers required by the P6 runtime without rewriting
 * any applied judging, score, result, or appeal migration.
 */
final readonly class CompleteCompetitionP6RuntimeContractsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912120000_complete_competition_p6_runtime_contracts');
    }

    public function description(): string
    {
        return 'Add append-only P6 assignment and result events plus notification intents.';
    }

    public function dependencies(): array
    {
        return [(new CorrectCompetitionP6LifecycleVocabularyMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('000_round_events'),
                'Create append-only round lifecycle evidence.',
                "CREATE TABLE competition_round_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, round_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_metadata JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_round_event_public(public_id), KEY ix_p6_round_event_round(workspace_id,round_id,id), CONSTRAINT fk_p6_round_event_round FOREIGN KEY(workspace_id,round_id) REFERENCES competition_rounds(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_round_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('001_assignment_events'),
                'Create append-only judge assignment lifecycle evidence.',
                "CREATE TABLE competition_judge_assignment_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, assignment_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_metadata JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_assignment_event_public(public_id), KEY ix_p6_assignment_event_assignment(workspace_id,assignment_id,id), CONSTRAINT fk_p6_assignment_event_assignment FOREIGN KEY(workspace_id,assignment_id) REFERENCES competition_judge_assignments(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_assignment_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_result_events'),
                'Create append-only result-run lifecycle evidence.',
                "CREATE TABLE competition_result_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, result_run_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_metadata JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_result_event_public(public_id), KEY ix_p6_result_event_run(workspace_id,result_run_id,id), CONSTRAINT fk_p6_result_event_run FOREIGN KEY(workspace_id,result_run_id) REFERENCES competition_result_runs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_result_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_notification_intents'),
                'Create durable transactional P6 notification intents.',
                "CREATE TABLE competition_notification_intents (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NULL, intent_type VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_public_id BINARY(16) NOT NULL, deduplication_key BINARY(32) NOT NULL, safe_payload JSON NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PENDING', attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, available_at DATETIME(6) NOT NULL, delivered_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_notification_public(public_id), UNIQUE KEY uq_p6_notification_dedup(workspace_id,deduplication_key), KEY ix_p6_notification_due(status,available_at,id), CONSTRAINT fk_p6_notification_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_notification_account FOREIGN KEY(account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p6_notification CHECK(status IN ('PENDING','DELIVERED','FAILED') AND attempts<=100)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_operations'),
                'Create replay-safe P6 mutation operation records.',
                "CREATE TABLE competition_p6_operations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, submission_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_fingerprint BINARY(32) NOT NULL, aggregate_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, aggregate_public_id BINARY(16) NOT NULL, result_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version_after INT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_operation_public(public_id), UNIQUE KEY uq_p6_operation_submission(submission_id), KEY ix_p6_operation_workspace(workspace_id,occurred_at,id), CONSTRAINT fk_p6_operation_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p6_operation_version CHECK(version_after>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            ),
            new SqlMigrationStep(new MigrationStepId('005_round_events_immutable'), 'Prevent round event updates.', "CREATE TRIGGER trg_p6_round_events_no_update BEFORE UPDATE ON competition_round_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Round events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('006_round_events_immutable'), 'Prevent round event deletion.', "CREATE TRIGGER trg_p6_round_events_no_delete BEFORE DELETE ON competition_round_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Round events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('007_assignment_events_immutable'), 'Prevent assignment event updates.', "CREATE TRIGGER trg_p6_assignment_events_no_update BEFORE UPDATE ON competition_judge_assignment_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Judge assignment events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('008_assignment_events_immutable'), 'Prevent assignment event deletion.', "CREATE TRIGGER trg_p6_assignment_events_no_delete BEFORE DELETE ON competition_judge_assignment_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Judge assignment events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('009_result_events_immutable'), 'Prevent result event updates.', "CREATE TRIGGER trg_p6_result_events_no_update BEFORE UPDATE ON competition_result_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Result events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('010_result_events_immutable'), 'Prevent result event deletion.', "CREATE TRIGGER trg_p6_result_events_no_delete BEFORE DELETE ON competition_result_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Result events are append-only'"),
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
