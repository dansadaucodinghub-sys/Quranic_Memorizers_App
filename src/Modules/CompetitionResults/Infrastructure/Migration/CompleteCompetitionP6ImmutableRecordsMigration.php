<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Migration;

use Qmdb\Modules\CompetitionJudging\Infrastructure\Migration\CreateCompetitionJudgingScoringMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only completion of P6 append-only and privacy records. */
final readonly class CompleteCompetitionP6ImmutableRecordsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912100000_complete_competition_p6_immutable_records');
    }
    public function description(): string
    {
        return 'Add P6 penalty, decision, consent, appeal-window, and append-only event records.';
    }
    public function dependencies(): array
    {
        return [(new CreateCompetitionJudgingScoringMigration())->id()];
    }
    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('000_penalty_rule_tenant_key'), 'Add penalty rule workspace candidate key.', 'ALTER TABLE competition_penalty_rules ADD UNIQUE KEY uq_p6_penalty_workspace_id (workspace_id,id)'),
            new SqlMigrationStep(new MigrationStepId('001_score_penalties'), 'Create sheet penalty entries.', "CREATE TABLE competition_score_penalties (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, score_sheet_id BIGINT UNSIGNED NOT NULL, penalty_rule_id BIGINT UNSIGNED NOT NULL, occurrences SMALLINT UNSIGNED NOT NULL, applied_units INT NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_score_penalty_public(public_id), UNIQUE KEY uq_p6_score_penalty_rule(workspace_id,score_sheet_id,penalty_rule_id), CONSTRAINT fk_p6_score_penalty_sheet FOREIGN KEY(workspace_id,score_sheet_id) REFERENCES competition_score_sheets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_score_penalty_rule FOREIGN KEY(workspace_id,penalty_rule_id) REFERENCES competition_penalty_rules(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p6_score_penalty CHECK(occurrences>=1 AND applied_units>=0)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('002_score_events'), 'Create append-only score sheet events.', "CREATE TABLE competition_score_sheet_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, score_sheet_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_metadata JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_score_event_public(public_id), KEY ix_p6_score_event_sheet(workspace_id,score_sheet_id,id), CONSTRAINT fk_p6_score_event_sheet FOREIGN KEY(workspace_id,score_sheet_id) REFERENCES competition_score_sheets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_score_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('003_disqualifications'), 'Create append-only disqualification decisions.', "CREATE TABLE competition_disqualifications (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, participant_id BIGINT UNSIGNED NOT NULL, decision_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_reason_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, decided_by_account_id BIGINT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_disqualification_public(public_id), KEY ix_p6_disqualification_participant(workspace_id,participant_id,id), CONSTRAINT fk_p6_disqualification_participant FOREIGN KEY(workspace_id,participant_id) REFERENCES competition_round_participants(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_disqualification_actor FOREIGN KEY(decided_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('004_public_consents'), 'Create append-only public result consent events.', "CREATE TABLE competition_public_result_consents (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, participant_id BIGINT UNSIGNED NOT NULL, action VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, public_label VARCHAR(200) NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_public_consent_public(public_id), KEY ix_p6_public_consent_participant(workspace_id,participant_id,id), CONSTRAINT fk_p6_public_consent_participant FOREIGN KEY(workspace_id,participant_id) REFERENCES competition_round_participants(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p6_public_consent CHECK(action IN ('GRANTED','REVOKED'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('005_appeal_windows'), 'Create controlled appeal windows.', "CREATE TABLE competition_appeal_windows (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, result_run_id BIGINT UNSIGNED NOT NULL, opens_at DATETIME(6) NOT NULL, closes_at DATETIME(6) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SCHEDULED', version INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY(id), UNIQUE KEY uq_p6_appeal_window_public(public_id), UNIQUE KEY uq_p6_appeal_window_run(workspace_id,result_run_id), CONSTRAINT fk_p6_appeal_window_run FOREIGN KEY(workspace_id,result_run_id) REFERENCES competition_result_runs(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p6_appeal_window CHECK(status IN ('SCHEDULED','OPEN','CLOSED','CANCELLED') AND closes_at>opens_at AND version>=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('099_appeal_tenant_key'), 'Add appeal workspace candidate key.', 'ALTER TABLE competition_appeals ADD UNIQUE KEY uq_p6_appeal_workspace_id (workspace_id,id)'),
            new SqlMigrationStep(new MigrationStepId('006_appeal_events'), 'Create append-only appeal decisions.', "CREATE TABLE competition_appeal_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, appeal_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_account_id BIGINT UNSIGNED NULL, safe_metadata JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p6_appeal_event_public(public_id), KEY ix_p6_appeal_event_appeal(workspace_id,appeal_id,id), CONSTRAINT fk_p6_appeal_event_appeal FOREIGN KEY(workspace_id,appeal_id) REFERENCES competition_appeals(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p6_appeal_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('007_score_events_immutable'), 'Prevent score sheet event updates.', "CREATE TRIGGER trg_p6_score_events_no_update BEFORE UPDATE ON competition_score_sheet_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Score sheet events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('008_score_events_immutable'), 'Prevent score sheet event deletion.', "CREATE TRIGGER trg_p6_score_events_no_delete BEFORE DELETE ON competition_score_sheet_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Score sheet events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('009_appeal_events_immutable'), 'Prevent appeal event updates.', "CREATE TRIGGER trg_p6_appeal_events_no_update BEFORE UPDATE ON competition_appeal_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appeal events are append-only'"),
            new SqlMigrationStep(new MigrationStepId('010_appeal_events_immutable'), 'Prevent appeal event deletion.', "CREATE TRIGGER trg_p6_appeal_events_no_delete BEFORE DELETE ON competition_appeal_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appeal events are append-only'")
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
