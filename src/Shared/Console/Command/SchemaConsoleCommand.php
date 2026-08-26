<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;

final readonly class SchemaConsoleCommand implements ConsoleCommand
{
    /** @param list<string> $allowedOptions */
    public function __construct(
        private ConsoleCommandName $commandName,
        private string $commandDescription,
        private SchemaConsoleApplication $application,
        private array $allowedOptions = [],
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return $this->commandName;
    }

    public function description(): string
    {
        return $this->commandDescription;
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions($this->allowedOptions);
        $arguments = [$this->commandName->value()];
        foreach ($this->allowedOptions as $name) {
            $value = $input->scalar($name);
            if ($value !== null) {
                $arguments[] = '--' . $name . '=' . $value;
            }
        }
        $result = $this->application->run($arguments);
        $output->write($result->standardOutput());
        $output->error($result->standardError());

        return $result->exitCode()->value;
    }
}
