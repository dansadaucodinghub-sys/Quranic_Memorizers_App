<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Console;

use PDO;
use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\MySqlCompositeForeignKeyVerifier;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only P6 verifier: checks schema, lifecycle correction, security catalog, and P7 boundary. */
final readonly class CompetitionP6VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('competition:p6:verify'); }
    public function description(): string { return 'Verify P6 judging, fixed-point scoring, results, appeals, and P7 boundary.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $required = ['competition_rounds','competition_round_participants','competition_round_events','competition_judges','competition_judge_panels','competition_judge_assignments','competition_judge_assignment_events','competition_judge_conflicts','competition_scoring_rubrics','competition_scoring_criteria','competition_penalty_rules','competition_tie_break_rules','competition_score_sheets','competition_score_entries','competition_score_penalties','competition_score_sheet_events','competition_result_runs','competition_result_rows','competition_result_events','competition_disqualifications','competition_public_result_consents','competition_appeal_windows','competition_appeals','competition_appeal_events','competition_notification_intents','competition_p6_operations'];
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
            if ($statement === false) { throw new \RuntimeException('P6 schema verification statement could not be prepared.'); }
            foreach ($required as $table) {
                $statement->execute([':table' => $table]);
                if ((int) $statement->fetchColumn() !== 1) { throw new \RuntimeException("Required P6 table is missing: {$table}"); }
            }
            (new MySqlCompositeForeignKeyVerifier())->verify($pdo);
            $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status='APPLIED' AND migration_id IN ('20260911140000_create_competition_judging_scoring','20260912100000_complete_competition_p6_immutable_records','20260912110000_correct_competition_p6_lifecycle_vocabulary','20260912120000_complete_competition_p6_runtime_contracts','20260912133000_add_competition_p6_result_input_uniqueness')", 5, 'P6 migrations are incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='competition_result_runs' AND index_name='uq_p6_run_input'", 3, 'P6 immutable result-input unique key is missing.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status='APPLIED' AND seed_id='20260912111000_correct_competition_p6_authorization_catalog'", 1, 'P6 authorization correction seed is not applied.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='competition.results' AND status='ACTIVE'", 14, 'P6 permission catalog is incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_roles WHERE code IN ('workspace.competition_judge','workspace.competition_head_judge','workspace.competition_result_manager','workspace.competition_appeal_reviewer','workspace.competition_result_auditor') AND status='ACTIVE'", 5, 'P6 specialist roles are incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE code IN ('workspace.competitions.score','workspace.competitions.review_appeals')", 0, 'Superseded P6 permissions remain active.');
            foreach (['ck_p6_round_lifecycle','ck_p6_participant_lifecycle','ck_p6_judge_lifecycle','ck_p6_assignment_lifecycle','ck_p6_conflict_lifecycle','ck_p6_run_lifecycle'] as $constraint) {
                $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_name=:constraint AND constraint_type=\'CHECK\'', 1, "P6 lifecycle check is missing: {$constraint}", [':constraint' => $constraint]);
            }
            foreach (['trg_p6_result_rows_no_update','trg_p6_result_rows_no_delete','trg_p6_round_events_no_update','trg_p6_round_events_no_delete','trg_p6_assignment_events_no_update','trg_p6_assignment_events_no_delete','trg_p6_score_events_no_update','trg_p6_score_events_no_delete','trg_p6_result_events_no_update','trg_p6_result_events_no_delete','trg_p6_appeal_events_no_update','trg_p6_appeal_events_no_delete'] as $trigger) {
                $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=:trigger', 1, "P6 immutability trigger is missing: {$trigger}", [':trigger' => $trigger]);
            }
            $forbidden = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '(^certificate|^media|^audio|^video|^social|payment|livestream|live_score)'");
            if ($forbidden === false || (int) $forbidden->fetchColumn() !== 0) { throw new \RuntimeException('A prohibited P7 persistence artifact exists.'); }
            $output->write("Competition P6 verification: PASS\nRequired tables: " . count($required) . "\nLifecycle checks: 6\nP6 permissions: 14\nP6 specialist roles: 5\nP7 artifacts: 0\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P6 verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }

    /** @param array<string, string> $parameters */
    private function requireCount(PDO $pdo, string $sql, int $expected, string $message, array $parameters = []): void
    {
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('P6 verification statement could not be prepared.');
        }
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() !== $expected) {
            throw new \RuntimeException($message);
        }
    }
}
