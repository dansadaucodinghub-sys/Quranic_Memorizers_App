<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Console;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class ScheduleListConsoleCommand implements ConsoleCommand
{
    public function __construct(private ScheduledTaskMap $tasks)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('schedule:list');
    }

    public function description(): string
    {
        return 'List explicitly registered scheduled tasks.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        if ($this->tasks->count() === 0) {
            $output->writeln('No scheduled tasks are registered.');

            return 0;
        }
        foreach ($this->tasks->tasks() as $task) {
            $output->writeln(implode(' | ', [
                $task->id()->value(),
                $task->description(),
                $task->schedule()->description(),
            ]));
        }

        return 0;
    }
}
