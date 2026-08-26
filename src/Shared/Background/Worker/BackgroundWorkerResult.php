<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class BackgroundWorkerResult
{
    public function __construct(
        private BackgroundWorkerIdentity $worker,
        private CorrelationId $correlationId,
        private BackgroundWorkerStopReason $stopReason,
        private int $processedCount,
        private int $failedCount,
    ) {
    }

    public function worker(): BackgroundWorkerIdentity
    {
        return $this->worker;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function stopReason(): BackgroundWorkerStopReason
    {
        return $this->stopReason;
    }

    public function processedCount(): int
    {
        return $this->processedCount;
    }

    public function failedCount(): int
    {
        return $this->failedCount;
    }

    public function isSuccessful(): bool
    {
        return !in_array($this->stopReason, [
            BackgroundWorkerStopReason::SOURCE_FAILURE,
            BackgroundWorkerStopReason::EXECUTION_FAILURE,
            BackgroundWorkerStopReason::CONFIGURATION_FAILURE,
        ], true);
    }
}
