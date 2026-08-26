<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class BackgroundJobEnvelope
{
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $availableAt;

    public function __construct(
        private BackgroundJobId $id,
        private BackgroundJobName $name,
        private BackgroundJob $job,
        private CorrelationId $correlationId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $availableAt,
        private int $attempt = 1,
        private int $maximumAttempts = 3,
    ) {
        $this->createdAt = UtcDateTime::normalize($createdAt);
        $this->availableAt = UtcDateTime::normalize($availableAt);
        if ($attempt < 1 || $maximumAttempts < 1 || $maximumAttempts > 100 || $attempt > $maximumAttempts) {
            throw new InvalidArgumentException('Background job attempt is invalid.');
        }
        if ($this->availableAt < $this->createdAt) {
            throw new InvalidArgumentException('Background job availability precedes creation.');
        }
    }

    public function id(): BackgroundJobId
    {
        return $this->id;
    }

    public function name(): BackgroundJobName
    {
        return $this->name;
    }

    public function job(): BackgroundJob
    {
        return $this->job;
    }

    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function availableAt(): DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function maximumAttempts(): int
    {
        return $this->maximumAttempts;
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'job_id' => $this->id->value(),
            'job_name' => $this->name->value(),
            'attempt' => $this->attempt,
            'maximum_attempts' => $this->maximumAttempts,
            'job' => '[object:redacted]',
        ];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Background job envelopes cannot be serialized by the foundation.');
    }
}
