<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class BootstrapFailureReporter
{
    public function __construct(private InternalErrorChannel $errorChannel)
    {
    }

    public function report(CorrelationId $correlationId, string $classification): void
    {
        $safeClassification = preg_match('/\A[a-z][a-z0-9_]{1,63}\z/D', $classification) === 1
            ? $classification
            : 'unknown';
        $this->errorChannel->write(sprintf(
            'qmdb bootstrap failure [request_id=%s] [type=%s]',
            $correlationId->value(),
            $safeClassification,
        ));
    }
}
