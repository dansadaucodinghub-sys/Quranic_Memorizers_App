<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use DateTimeImmutable;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class BackgroundJobExecutionContext
{
    private DateTimeImmutable $startedAt;

    public function __construct(
        private BackgroundWorkerIdentity $worker,
        private BackgroundJobId $jobId,
        private CorrelationId $correlationId,
        private int $attempt,
        private int $maximumAttempts,
        DateTimeImmutable $startedAt,
        private bool $stopRequested,
    ) {
        $this->startedAt = UtcDateTime::normalize($startedAt);
    }

    public function worker(): BackgroundWorkerIdentity
    {
        return $this->worker;
    }

    public function jobId(): BackgroundJobId
    {
        return $this->jobId;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function maximumAttempts(): int
    {
        return $this->maximumAttempts;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function stopRequested(): bool
    {
        return $this->stopRequested;
    }
}
