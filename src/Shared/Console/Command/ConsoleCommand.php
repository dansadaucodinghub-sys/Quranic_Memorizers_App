<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

interface ConsoleCommand
{
    public function name(): ConsoleCommandName;

    public function description(): string;

    public function execute(ConsoleInput $input, ConsoleOutput $output): int;
}
