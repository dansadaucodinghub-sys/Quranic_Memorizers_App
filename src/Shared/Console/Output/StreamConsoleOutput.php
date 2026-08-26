<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Output;

use InvalidArgumentException;
use RuntimeException;

final readonly class StreamConsoleOutput implements ConsoleOutput
{
    /** @var resource */
    private mixed $standardOutput;
    /** @var resource */
    private mixed $standardError;

    /** @param resource $standardOutput @param resource $standardError */
    public function __construct(mixed $standardOutput, mixed $standardError)
    {
        if (!is_resource($standardOutput) || !is_resource($standardError)) {
            throw new InvalidArgumentException('Console output streams are invalid.');
        }
        $this->standardOutput = $standardOutput;
        $this->standardError = $standardError;
    }

    public function write(string $value): void
    {
        $this->writeTo($this->standardOutput, $value);
    }

    public function writeln(string $value = ''): void
    {
        $this->write($value . "\n");
    }

    public function error(string $value): void
    {
        $this->writeTo($this->standardError, $value);
    }

    public function errorLine(string $value = ''): void
    {
        $this->error($value . "\n");
    }

    /** @param resource $stream */
    private function writeTo(mixed $stream, string $value): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Console output stream is no longer valid.');
        }
        if (@fwrite($stream, $value) === false) {
            throw new RuntimeException('Console output could not be written.');
        }
    }
}
