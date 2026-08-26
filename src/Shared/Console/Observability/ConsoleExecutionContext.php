<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Observability;

use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class ConsoleExecutionContext
{
    public function __construct(
        private CorrelationId $correlationId,
        private string $command,
        private int $startedAtNanoseconds,
    ) {
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function startedAtNanoseconds(): int
    {
        return $this->startedAtNanoseconds;
    }
}
