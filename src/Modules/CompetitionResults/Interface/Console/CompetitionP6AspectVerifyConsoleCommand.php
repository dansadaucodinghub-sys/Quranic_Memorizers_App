<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Console;

use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;

/** Provides narrow operational verifier names while preserving one schema truth source. */
final readonly class CompetitionP6AspectVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $summary, private DatabaseConnectionProvider $connections, private ScheduledTaskMap $scheduledTasks)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }
    public function description(): string
    {
        return $this->summary;
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        return (new CompetitionP6VerifyConsoleCommand($this->connections, $this->scheduledTasks))->execute($input, $output);
    }
}
