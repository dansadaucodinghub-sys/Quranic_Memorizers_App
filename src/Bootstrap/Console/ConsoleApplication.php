<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Console;

use InvalidArgumentException;
use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Shared\Console\Command\ConsoleCommandDispatcher;
use Qmdb\Shared\Console\Command\ConsoleCommandMap;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Command\ConsoleCommandRegistry;
use Qmdb\Shared\Console\Command\AppAboutConsoleCommand;
use Qmdb\Shared\Console\Command\SchemaConsoleCommand;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Input\ConsoleInputParser;
use Qmdb\Shared\Console\Observability\ConsoleExecutionObserver;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;
use Throwable;

final readonly class ConsoleApplication
{
    private ConsoleInputParser $inputParser;
    private ConsoleCommandMap $commands;
    private ConsoleCommandDispatcher $dispatcher;

    public function __construct(
        private Application $application,
        ?SchemaConsoleApplication $schemaConsole = null,
        private ?ConsoleExecutionObserver $executionObserver = null,
        private ?ErrorHandlingRuntime $errorHandlingRuntime = null,
        ?ConsoleInputParser $inputParser = null,
        ?ConsoleCommandMap $commands = null,
        ?ConsoleCommandDispatcher $dispatcher = null,
    ) {
        $this->inputParser = $inputParser ?? new ConsoleInputParser();
        $this->commands = $commands ?? self::compatibilityCommands($application, $schemaConsole);
        $this->dispatcher = $dispatcher ?? new ConsoleCommandDispatcher($this->commands);
    }

    /**
     * @param list<string> $arguments
     * @param list<string>|null $loadedExtensions
     */
    public function run(
        array $arguments,
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
    ): ConsoleResult {
        try {
            $input = $this->inputParser->parse($arguments);
        } catch (InvalidArgumentException $exception) {
            return new ConsoleResult(
                ExitCode::INVALID_USAGE,
                standardError: $exception->getMessage() . "\n",
            );
        }

        $commandName = $input->commandName()->value() === 'help:show'
            ? 'help'
            : $input->commandName()->value();
        $observer = $this->executionObserver;
        $execution = $observer?->start($commandName);
        $this->errorHandlingRuntime?->register();

        try {
            $result = $this->execute($input, $phpVersion, $loadedExtensions);
        } catch (Throwable $throwable) {
            if ($execution === null) {
                throw $throwable;
            }
            $observer->fail($execution, $throwable, ExitCode::FAILURE->value);

            return new ConsoleResult(
                ExitCode::FAILURE,
                standardError: "The operation could not be completed.\nReference: "
                    . $execution->correlationId()->value() . "\n",
            );
        } finally {
            $this->errorHandlingRuntime?->unregister();
        }

        if ($execution !== null) {
            $observer?->complete($execution, $result->exitCode()->value);
        }

        return $result;
    }

    /** @param list<string>|null $loadedExtensions */
    private function execute(
        ConsoleInput $input,
        ?string $phpVersion,
        ?array $loadedExtensions,
    ): ConsoleResult {
        $runtime = $this->application->validateRuntime($phpVersion, $loadedExtensions);
        if (!$runtime->isSatisfied()) {
            return new ConsoleResult(
                ExitCode::FAILURE,
                standardError: $runtime->toCliString() . "\n",
            );
        }
        if ($input->commandName()->value() === 'help:show') {
            return new ConsoleResult(ExitCode::SUCCESS, $this->helpText());
        }
        if ($this->commands->find($input->commandName()) === null) {
            return new ConsoleResult(
                ExitCode::INVALID_USAGE,
                standardError: "Unknown command. Run 'php bin/console help' for usage.\n",
            );
        }

        $output = new BufferedConsoleOutput();
        try {
            $exitCode = $this->dispatcher->dispatch($input, $output);
        } catch (InvalidArgumentException $exception) {
            $output->errorLine($exception->getMessage());
            $exitCode = ExitCode::INVALID_USAGE->value;
        }
        if ($input->commandName()->value() === 'app:about' && $phpVersion !== null) {
            $rendered = preg_replace(
                '/^Current PHP Version: .*$/m',
                'Current PHP Version: ' . $phpVersion,
                $output->standardOutput(),
            );
            if (is_string($rendered)) {
                $replacement = new BufferedConsoleOutput();
                $replacement->write($rendered);
                $replacement->error($output->standardError());
                $output = $replacement;
            }
        }

        return new ConsoleResult(
            ExitCode::tryFrom($exitCode) ?? ExitCode::FAILURE,
            $output->standardOutput(),
            $output->standardError(),
        );
    }

    private function helpText(): string
    {
        $lines = [
            'Usage: php bin/console <command> [options]',
            '',
            'Available commands:',
        ];
        foreach ($this->commands->commands() as $command) {
            $lines[] = '  ' . str_pad($command->name()->value(), 24) . $command->description();
        }
        $lines[] = '  ' . str_pad('help', 24) . 'Show this help message.';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    private static function compatibilityCommands(
        Application $application,
        ?SchemaConsoleApplication $schemaConsole,
    ): ConsoleCommandMap {
        $registry = new ConsoleCommandRegistry();
        $registry->register(new AppAboutConsoleCommand($application));
        if ($schemaConsole !== null) {
            foreach (self::schemaCommandNames() as $name) {
                $options = $name === 'db:migrate:rollback' ? ['migration', 'confirm'] : [];
                $registry->register(new SchemaConsoleCommand(
                    new ConsoleCommandName($name),
                    'Execute a governed schema operation.',
                    $schemaConsole,
                    $options,
                ));
            }
        }

        return $registry->build();
    }

    /** @return list<string> */
    private static function schemaCommandNames(): array
    {
        return [
            'db:schema:install',
            'db:schema:verify',
            'db:migrate:plan',
            'db:migrate',
            'db:migrate:status',
            'db:migrate:rollback',
            'db:seed',
            'db:seed:status',
        ];
    }
}
