<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use Monolog\LogRecord;

final readonly class StaticApplicationContextProcessor
{
    /** @param array{application: string, environment: string, phase: string, batch: string, baseline: string} $context */
    public function __construct(private array $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [...$record->extra, ...$this->context]);
    }
}
