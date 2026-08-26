<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Throwable;

interface ThrowableReporter
{
    /** @param array<string, mixed> $safeContext */
    public function report(Throwable $throwable, CorrelationId $correlationId, array $safeContext = []): void;
}
