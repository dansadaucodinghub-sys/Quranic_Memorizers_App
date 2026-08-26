<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Output;

interface ConsoleOutput
{
    public function write(string $value): void;

    public function writeln(string $value = ''): void;

    public function error(string $value): void;

    public function errorLine(string $value = ''): void;
}
