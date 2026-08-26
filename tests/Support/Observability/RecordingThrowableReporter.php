<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Error\ThrowableReporter;
use Throwable;

final class RecordingThrowableReporter implements ThrowableReporter
{
    /** @var list<array{throwable: Throwable, request_id: string, context: array<string, mixed>}> */
    private array $reports = [];

    public function report(Throwable $throwable, CorrelationId $correlationId, array $safeContext = []): void
    {
        $this->reports[] = [
            'throwable' => $throwable,
            'request_id' => $correlationId->value(),
            'context' => $safeContext,
        ];
    }

    /** @return list<array{throwable: Throwable, request_id: string, context: array<string, mixed>}> */
    public function reports(): array
    {
        return $this->reports;
    }
}
