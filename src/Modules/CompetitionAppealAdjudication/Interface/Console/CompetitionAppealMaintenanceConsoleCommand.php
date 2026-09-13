<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Interface\Console;

use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence\CompetitionAppealMaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

/** Bounded read-only P7 appeal maintenance commands. */
final readonly class CompetitionAppealMaintenanceConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private CompetitionAppealMaintenanceService $maintenance)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P7 appeal ' . $this->operation . ' maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run', 'limit']);
        $dryRun = $input->requireFlag('dry-run');
        $limit = $this->limit($input->scalar('limit'));
        $count = match ($this->operation) {
            'process' => $this->maintenance->process($limit, $dryRun),
            'verify' => $this->maintenance->reconcile($limit),
            default => throw new \LogicException('Appeal maintenance console operation is invalid.'),
        };
        $output->writeln('Processed: ' . $count);

        return 0;
    }

    private function limit(?string $value): int
    {
        if ($value === null) {
            return 100;
        }
        if (preg_match('/^[1-9][0-9]{0,2}$/', $value) !== 1 || (int) $value > 500) {
            throw new \InvalidArgumentException('Appeal maintenance limit is invalid.');
        }

        return (int) $value;
    }
}
