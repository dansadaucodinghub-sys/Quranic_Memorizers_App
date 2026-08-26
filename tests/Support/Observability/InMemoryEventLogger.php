<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;

final class InMemoryEventLogger implements EventLogger
{
    /** @var list<array{level: LogLevel, event: string, context: array<string, mixed>}> */
    private array $records = [];

    public function log(LogLevel $level, LogEventName $event, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'event' => $event->value(),
            'context' => $context,
        ];
    }

    /** @return list<array{level: LogLevel, event: string, context: array<string, mixed>}> */
    public function records(): array
    {
        return $this->records;
    }
}
