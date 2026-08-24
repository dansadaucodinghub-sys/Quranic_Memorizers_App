<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Console;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Shared\ExitCode;

final readonly class ConsoleApplication
{
    private const HELP_COMMANDS = ['help', '--help', '-h'];

    public function __construct(private Application $application)
    {
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
        $command = $arguments[0] ?? 'help';

        if ($command !== 'app:about' && !in_array($command, self::HELP_COMMANDS, true)) {
            return new ConsoleResult(
                exitCode: ExitCode::INVALID_USAGE,
                standardError: "Unknown command. Run 'php bin/console help' for usage.\n",
            );
        }

        $runtimeResult = $this->application->validateRuntime($phpVersion, $loadedExtensions);

        if (!$runtimeResult->isSatisfied()) {
            return new ConsoleResult(
                exitCode: ExitCode::FAILURE,
                standardError: $runtimeResult->toCliString() . "\n",
            );
        }

        if (in_array($command, self::HELP_COMMANDS, true)) {
            return new ConsoleResult(
                exitCode: ExitCode::SUCCESS,
                standardOutput: $this->helpText(),
            );
        }

        $effectivePhpVersion = $phpVersion ?? PHP_VERSION;
        $lines = $this->application->metadata()->toCliLines();
        $lines[] = 'Current PHP Version: ' . $effectivePhpVersion;
        $lines[] = 'Runtime Requirements: satisfied';

        return new ConsoleResult(
            exitCode: ExitCode::SUCCESS,
            standardOutput: implode("\n", $lines) . "\n",
        );
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Usage: php bin/console <command>',
            '',
            'Available commands:',
            '  app:about  Show safe application and runtime information.',
            '  help       Show this help message.',
            '',
        ]);
    }
}
