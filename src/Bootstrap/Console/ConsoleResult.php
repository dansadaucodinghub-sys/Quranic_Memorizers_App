<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Console;

use Qmdb\Bootstrap\Shared\ExitCode;

final readonly class ConsoleResult
{
    public function __construct(
        private ExitCode $exitCode,
        private string $standardOutput = '',
        private string $standardError = '',
    ) {
    }

    public function exitCode(): ExitCode
    {
        return $this->exitCode;
    }

    public function standardOutput(): string
    {
        return $this->standardOutput;
    }

    public function standardError(): string
    {
        return $this->standardError;
    }
}
