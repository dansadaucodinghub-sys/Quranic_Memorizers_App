<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use LogicException;

final class ConsoleCommandRegistry
{
    /** @var list<ConsoleCommand> */
    private array $commands = [];
    private bool $frozen = false;

    public function register(ConsoleCommand $command): self
    {
        if ($this->frozen) {
            throw new LogicException('Console command registry is frozen.');
        }
        $this->commands[] = $command;

        return $this;
    }

    public function build(): ConsoleCommandMap
    {
        $this->frozen = true;

        return new ConsoleCommandMap($this->commands);
    }
}
