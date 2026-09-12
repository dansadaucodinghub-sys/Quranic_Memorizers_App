<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only P6 verifier: checks schema presence and keeps P7 artifacts out. */
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
            $required = ['competition_rounds','competition_round_participants','competition_judges','competition_judge_panels','competition_judge_assignments','competition_judge_conflicts','competition_scoring_rubrics','competition_scoring_criteria','competition_penalty_rules','competition_tie_break_rules','competition_score_sheets','competition_score_entries','competition_result_runs','competition_result_rows','competition_appeals'];
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
            if ($statement === false) { throw new \RuntimeException('P6 schema verification statement could not be prepared.'); }
            foreach ($required as $table) {
                $statement->execute([':table' => $table]);
                if ((int) $statement->fetchColumn() !== 1) { throw new \RuntimeException("Required P6 table is missing: {$table}"); }
            }
            $forbidden = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '(^certificate|^media|^audio|^video|^social|payment|livestream|live_score)'");
            if ($forbidden === false || (int) $forbidden->fetchColumn() !== 0) { throw new \RuntimeException('A prohibited P7 persistence artifact exists.'); }
            $output->write("Competition P6 verification: PASS\nRequired tables: " . count($required) . "\nP7 artifacts: 0\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P6 verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
