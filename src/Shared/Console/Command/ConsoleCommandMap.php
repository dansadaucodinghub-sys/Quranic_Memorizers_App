<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use InvalidArgumentException;

final readonly class ConsoleCommandMap
{
    /** @var array<string, ConsoleCommand> */
    private array $commands;

    /** @param list<ConsoleCommand> $commands */
    public function __construct(array $commands)
    {
        $indexed = [];
        foreach ($commands as $command) {
            $name = $command->name()->value();
            if (isset($indexed[$name])) {
                throw new InvalidArgumentException('Duplicate console command name.');
            }
            $description = trim($command->description());
            if ($description === '' || strlen($description) > 160 || preg_match('/[\x00-\x1F\x7F]/', $description)) {
                throw new InvalidArgumentException('Console command description is invalid.');
            }
            $indexed[$name] = $command;
        }
        ksort($indexed);
        $this->commands = $indexed;
    }

    public function find(ConsoleCommandName $name): ?ConsoleCommand
    {
        return $this->commands[$name->value()] ?? null;
    }

    /** @return list<ConsoleCommand> */
    public function commands(): array
    {
        return array_values($this->commands);
    }
}
