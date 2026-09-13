<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Migration;

use Qmdb\Modules\CompetitionPublication\Infrastructure\Migration\CreateCompetitionResultPublicationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** P7 adds independent review evidence without duplicating the P6 appeal aggregate. */
final readonly class CreateCompetitionAppealAdjudicationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912144000_create_competition_appeal_adjudication');
    }

    public function description(): string
    {
        return 'Create P7 independent appeal review, decision, conflict, and correction evidence.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionResultPublicationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_review_assignments'), 'Create independent appeal-review assignments.', "CREATE TABLE competition_appeal_review_assignments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, appeal_id BIGINT UNSIGNED NOT NULL, reviewer_account_id BIGINT UNSIGNED NOT NULL, reviewer_role VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ASSIGNED', primary_reviewer_marker TINYINT GENERATED ALWAYS AS(CASE WHEN reviewer_role='PRIMARY_REVIEWER' AND status='ACCEPTED' THEN 1 ELSE NULL END) STORED, version INT UNSIGNED NOT NULL DEFAULT 1, assigned_by_account_id BIGINT UNSIGNED NOT NULL, assigned_at DATETIME(6) NOT NULL, accepted_at DATETIME(6) NULL, declined_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, completed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_appeal_review_assignment_public(public_id), UNIQUE KEY uq_p7_appeal_review_assignment_reviewer(workspace_id,appeal_id,reviewer_account_id), UNIQUE KEY uq_p7_appeal_review_assignment_primary(workspace_id,appeal_id,primary_reviewer_marker), UNIQUE KEY uq_p7_appeal_review_assignment_workspace_id(workspace_id,id), KEY ix_p7_appeal_review_assignment_queue(workspace_id,status,assigned_at,id), CONSTRAINT fk_p7_appeal_review_assignment_appeal FOREIGN KEY(workspace_id,appeal_id) REFERENCES competition_appeals(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_appeal_review_assignment_reviewer FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_appeal_review_assignment_assigner FOREIGN KEY(assigned_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_appeal_review_assignment CHECK(reviewer_role IN ('PRIMARY_REVIEWER','SECONDARY_REVIEWER','OBSERVER') AND status IN ('ASSIGNED','ACCEPTED','DECLINED','REVOKED','COMPLETED') AND version>=1 AND (status <> 'ACCEPTED' OR accepted_at IS NOT NULL) AND (status <> 'DECLINED' OR declined_at IS NOT NULL) AND (status <> 'REVOKED' OR revoked_at IS NOT NULL) AND (status <> 'COMPLETED' OR completed_at IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('002_reviewer_conflicts'), 'Create append-only reviewer conflict declarations.', "CREATE TABLE competition_appeal_reviewer_conflicts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, review_assignment_id BIGINT UNSIGNED NOT NULL, conflict_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DECLARED', safe_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, declared_at DATETIME(6) NOT NULL, resolved_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_appeal_reviewer_conflict_public(public_id), KEY ix_p7_appeal_reviewer_conflict_assignment(workspace_id,review_assignment_id,status,id), CONSTRAINT fk_p7_appeal_reviewer_conflict_assignment FOREIGN KEY(workspace_id,review_assignment_id) REFERENCES competition_appeal_review_assignments(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_appeal_reviewer_conflict CHECK(conflict_type IN ('APPELLANT','COMPETITOR','ORIGINAL_JUDGE','RESULT_OPERATOR','ORGANIZATION','PERSONAL','OTHER') AND status IN ('DECLARED','CLEARED','RECUSED') AND (status='DECLARED' OR resolved_at IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('003_appeal_decisions'), 'Create immutable independent appeal decisions.', "CREATE TABLE competition_appeal_decisions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, appeal_id BIGINT UNSIGNED NOT NULL, decision_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, confidential_summary_ciphertext MEDIUMBLOB NOT NULL, confidential_summary_key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, correction_effects_canonical_json JSON NOT NULL, correction_authorization_sha256 BINARY(32) NOT NULL, decided_by_account_id BIGINT UNSIGNED NOT NULL, decided_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_appeal_decision_public(public_id), UNIQUE KEY uq_p7_appeal_decision_appeal(workspace_id,appeal_id), UNIQUE KEY uq_p7_appeal_decision_workspace_id(workspace_id,id), CONSTRAINT fk_p7_appeal_decision_appeal FOREIGN KEY(workspace_id,appeal_id) REFERENCES competition_appeals(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_appeal_decision_decider FOREIGN KEY(decided_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_appeal_decision CHECK(decision_type IN ('UPHELD','PARTIALLY_UPHELD','DISMISSED'))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('004_correction_authorizations'), 'Create forward-only appeal correction authorizations.', "CREATE TABLE competition_appeal_correction_authorizations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, appeal_decision_id BIGINT UNSIGNED NOT NULL, authorization_sha256 BINARY(32) NOT NULL, correction_effect VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'AUTHORIZED', consumed_by_account_id BIGINT UNSIGNED NULL, consumed_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p7_appeal_correction_public(public_id), UNIQUE KEY uq_p7_appeal_correction_decision(workspace_id,appeal_decision_id,correction_effect), KEY ix_p7_appeal_correction_queue(workspace_id,status,id), CONSTRAINT fk_p7_appeal_correction_decision FOREIGN KEY(workspace_id,appeal_decision_id) REFERENCES competition_appeal_decisions(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT fk_p7_appeal_correction_consumer FOREIGN KEY(consumed_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p7_appeal_correction CHECK(correction_effect IN ('NO_CHANGE','SCORE_SHEET_CORRECTION_REQUIRED','DISQUALIFICATION_REVIEW_REQUIRED','RESULT_RECALCULATION_REQUIRED','PUBLICATION_CORRECTION_REQUIRED') AND status IN ('AUTHORIZED','CONSUMED','REVOKED') AND ((status='CONSUMED' AND consumed_by_account_id IS NOT NULL AND consumed_at IS NOT NULL) OR (status<>'CONSUMED')))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"),
            new SqlMigrationStep(new MigrationStepId('005_decision_no_update'), 'Prevent decision modification.', "CREATE TRIGGER trg_p7_appeal_decisions_no_update BEFORE UPDATE ON competition_appeal_decisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appeal decisions are append-only'"),
            new SqlMigrationStep(new MigrationStepId('006_decision_no_delete'), 'Prevent decision deletion.', "CREATE TRIGGER trg_p7_appeal_decisions_no_delete BEFORE DELETE ON competition_appeal_decisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appeal decisions are append-only'"),
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
