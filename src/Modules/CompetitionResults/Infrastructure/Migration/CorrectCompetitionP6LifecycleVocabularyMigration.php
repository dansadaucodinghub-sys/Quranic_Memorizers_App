<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Migration;

use Qmdb\Modules\CompetitionResults\Infrastructure\Migration\CompleteCompetitionP6ImmutableRecordsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Forward-only repair for vocabulary created by the initial P6 foundation.
 * Historical values are deterministically translated before the new checks apply.
 */
final readonly class CorrectCompetitionP6LifecycleVocabularyMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260912110000_correct_competition_p6_lifecycle_vocabulary');
    }

    public function description(): string
    {
        return 'Correct P6 lifecycle vocabulary without altering applied migrations.';
    }

    public function dependencies(): array
    {
        return [(new CompleteCompetitionP6ImmutableRecordsMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_round_status_unconstrain'), 'Remove the superseded round lifecycle check.', 'ALTER TABLE competition_rounds DROP CHECK ck_p6_round'),
            new SqlMigrationStep(new MigrationStepId('002_round_status_width'), 'Allow explicit P6 round lifecycle names.', 'ALTER TABLE competition_rounds MODIFY status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'DRAFT\''),
            new SqlMigrationStep(new MigrationStepId('003_round_status_translate'), 'Translate legacy P6 round states.', "UPDATE competition_rounds SET status = CASE status WHEN 'OPEN' THEN 'SCORING_OPEN' WHEN 'CLOSED' THEN 'SCORING_CLOSED' ELSE status END"),
            new SqlMigrationStep(new MigrationStepId('004_round_status_constrain'), 'Enforce the complete P6 round lifecycle vocabulary.', "ALTER TABLE competition_rounds ADD CONSTRAINT ck_p6_round_lifecycle CHECK(round_order>=1 AND judging_mode IN ('BLIND','IDENTIFIED') AND status IN ('DRAFT','READY','SCORING_OPEN','SCORING_CLOSED','RESULTS_CALCULATED','RESULTS_VERIFIED','RESULTS_PUBLISHED','CANCELLED') AND version>=1)"),
            new SqlMigrationStep(new MigrationStepId('005_participant_status_unconstrain'), 'Remove the superseded participant status check.', 'ALTER TABLE competition_round_participants DROP CHECK ck_p6_participant'),
            new SqlMigrationStep(new MigrationStepId('006_participant_status_constrain'), 'Enforce full participant lifecycle vocabulary.', "ALTER TABLE competition_round_participants ADD CONSTRAINT ck_p6_participant_lifecycle CHECK(participant_order>=1 AND status IN ('SEEDED','ACTIVE','COMPLETED','ADVANCED','ELIMINATED','WITHDRAWN','DISQUALIFIED','CANCELLED') AND advancement_basis IN ('ROSTER','PRIOR_RESULT','MANUAL_AUTHORIZED'))"),
            new SqlMigrationStep(new MigrationStepId('007_judge_status_unconstrain'), 'Remove the superseded judge status check.', 'ALTER TABLE competition_judges DROP CHECK ck_p6_judge'),
            new SqlMigrationStep(new MigrationStepId('008_judge_status_constrain'), 'Allow judge suspension without removing historical evidence.', "ALTER TABLE competition_judges ADD CONSTRAINT ck_p6_judge_lifecycle CHECK(status IN ('ACTIVE','SUSPENDED','RETIRED') AND version>=1)"),
            new SqlMigrationStep(new MigrationStepId('009_assignment_unconstrain'), 'Remove the superseded judge assignment check.', 'ALTER TABLE competition_judge_assignments DROP CHECK ck_p6_assignment'),
            new SqlMigrationStep(new MigrationStepId('010_assignment_translate'), 'Translate initial assignment vocabulary.', "UPDATE competition_judge_assignments SET assignment_role = CASE assignment_role WHEN 'HEAD' THEN 'HEAD_JUDGE' WHEN 'MEMBER' THEN 'JUDGE' ELSE assignment_role END, status = CASE status WHEN 'INVITED' THEN 'ASSIGNED' ELSE status END"),
            new SqlMigrationStep(new MigrationStepId('011_assignment_head_marker'), 'Rebuild the generated head marker for corrected role values.', 'ALTER TABLE competition_judge_assignments DROP COLUMN head_marker, ADD COLUMN head_marker TINYINT GENERATED ALWAYS AS(CASE WHEN assignment_role=\'HEAD_JUDGE\' AND status=\'ACCEPTED\' THEN 1 ELSE NULL END) STORED'),
            new SqlMigrationStep(new MigrationStepId('012_assignment_constrain'), 'Enforce P6 assignment roles and lifecycle states.', "ALTER TABLE competition_judge_assignments ADD CONSTRAINT ck_p6_assignment_lifecycle CHECK(assignment_role IN ('HEAD_JUDGE','JUDGE','OBSERVER') AND status IN ('ASSIGNED','ACCEPTED','DECLINED','REVOKED','COMPLETED') AND version>=1)"),
            new SqlMigrationStep(new MigrationStepId('013_conflict_unconstrain'), 'Remove the superseded conflict type check.', 'ALTER TABLE competition_judge_conflicts DROP CHECK ck_p6_conflict'),
            new SqlMigrationStep(new MigrationStepId('014_conflict_constrain'), 'Enforce the full controlled conflict vocabulary.', "ALTER TABLE competition_judge_conflicts ADD CONSTRAINT ck_p6_conflict_lifecycle CHECK(conflict_type IN ('FAMILY','TEACHER_STUDENT','ORGANIZATION','FINANCIAL','PERSONAL','OTHER') AND status IN ('DECLARED','CLEARED','RECUSED'))"),
            new SqlMigrationStep(new MigrationStepId('015_result_status_unconstrain'), 'Remove the superseded result-run status check.', 'ALTER TABLE competition_result_runs DROP CHECK ck_p6_run'),
            new SqlMigrationStep(new MigrationStepId('016_result_status_constrain'), 'Allow VOIDED immutable result snapshots.', "ALTER TABLE competition_result_runs ADD CONSTRAINT ck_p6_run_lifecycle CHECK(status IN ('CALCULATED','VERIFIED','PUBLISHED','SUPERSEDED','VOIDED'))"),
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
