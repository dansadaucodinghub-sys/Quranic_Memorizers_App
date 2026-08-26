<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use Qmdb\Shared\Configuration\Logging\LogLevel;

interface EventLogger
{
    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, LogEventName $event, array $context = []): void;
}
