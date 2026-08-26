<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use InvalidArgumentException;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class ConsoleCommandDispatcher
{
    public function __construct(private ConsoleCommandMap $commands)
    {
    }

    public function dispatch(ConsoleInput $input, ConsoleOutput $output): int
    {
        $command = $this->commands->find($input->commandName());
        if ($command === null) {
            throw new InvalidArgumentException('Unknown command.');
        }

        return $command->execute($input, $output);
    }
}
