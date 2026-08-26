<?php

declare(strict_types=1);

namespace Qmdb\Tools\Support;

final readonly class ProcessResult
{
    /** @param list<string> $command */
    public function __construct(
        public array $command,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function output(): string
    {
        return trim($this->stdout . ($this->stderr === '' ? '' : "\n" . $this->stderr));
    }
}
