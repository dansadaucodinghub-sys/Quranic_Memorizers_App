<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Console;

use Qmdb\Shared\Background\Scheduler\Scheduler;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class ScheduleRunConsoleCommand implements ConsoleCommand
{
    public function __construct(private Scheduler $scheduler)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('schedule:run');
    }

    public function description(): string
    {
        return 'Evaluate and execute due scheduled tasks.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $result = $this->scheduler->run();
        $output->writeln('Due: ' . $result->due);
        $output->writeln('Claimed: ' . $result->claimed);
        $output->writeln('Succeeded: ' . $result->succeeded);
        $output->writeln('Failed: ' . $result->failed);
        $output->writeln('Skipped: ' . $result->skipped);

        return $result->isSuccessful() ? 0 : 1;
    }
}
