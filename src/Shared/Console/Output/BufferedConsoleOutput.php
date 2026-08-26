<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Output;

final class BufferedConsoleOutput implements ConsoleOutput
{
    private string $standardOutput = '';
    private string $standardError = '';

    public function write(string $value): void
    {
        $this->standardOutput .= $value;
    }

    public function writeln(string $value = ''): void
    {
        $this->write($value . "\n");
    }

    public function error(string $value): void
    {
        $this->standardError .= $value;
    }

    public function errorLine(string $value = ''): void
    {
        $this->error($value . "\n");
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
