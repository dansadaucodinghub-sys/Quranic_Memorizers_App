<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use DateTimeImmutable;
use Qmdb\Shared\Background\Job\BackgroundJobFailure;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Background\Source\BackgroundJobSource;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;

final class InMemoryBackgroundJobSource implements BackgroundJobSource
{
    /** @var list<ReservedBackgroundJob> */
    private array $jobs;
    /** @var list<string> */
    public array $actions = [];
    /** @var list<DateTimeImmutable> */
    public array $releasedAt = [];
    public int $reserveCalls = 0;

    /** @param list<ReservedBackgroundJob> $jobs */
    public function __construct(
        array $jobs = [],
        private bool $failReserve = false,
        private bool $failAcknowledge = false,
    ) {
        $this->jobs = $jobs;
    }

    public function reserve(
        BackgroundWorkerIdentity $worker,
        DateTimeImmutable $now,
    ): ?ReservedBackgroundJob {
        ++$this->reserveCalls;
        if ($this->failReserve) {
            throw new \RuntimeException('QMDB_SOURCE_FAILURE_SECRET');
        }

        return array_shift($this->jobs);
    }

    public function acknowledge(ReservedBackgroundJob $job): void
    {
        if ($this->failAcknowledge) {
            throw new \RuntimeException('QMDB_ACKNOWLEDGE_FAILURE_SECRET');
        }
        $this->actions[] = 'acknowledge';
    }

    public function release(
        ReservedBackgroundJob $job,
        DateTimeImmutable $availableAt,
        BackgroundJobFailure $failure,
    ): void {
        $this->actions[] = 'release:' . $failure->code()->value();
        $this->releasedAt[] = $availableAt;
    }

    public function fail(ReservedBackgroundJob $job, BackgroundJobFailure $failure): void
    {
        $this->actions[] = 'fail:' . $failure->code()->value();
    }
}
