<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Console;

use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\CompetitionP6MaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

/** One bounded CLI surface for each closed P6 maintenance operation. */
final readonly class CompetitionP6MaintenanceConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private CompetitionP6MaintenanceService $maintenance)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }
    public function description(): string
    {
        return 'Run bounded P6 ' . str_replace(':', ' ', $this->operation) . ' maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        $dryRun = $input->requireFlag('dry-run');
        $result = match ($this->operation) {
            'rounds' => $this->maintenance->processRounds($dryRun),
            'reminders' => $this->maintenance->remindScoreSheets($dryRun),
            'appeal-windows' => $this->maintenance->processAppealWindows($dryRun),
            'score-reconciliation' => $this->maintenance->reconcileScoreSheets(),
            'result-reconciliation' => $this->maintenance->reconcileResults(),
            default => throw new \LogicException('P6 maintenance CLI operation is invalid.'),
        };
        foreach ($result as $key => $value) {
            $output->writeln(ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
        }

        return 0;
    }
}
