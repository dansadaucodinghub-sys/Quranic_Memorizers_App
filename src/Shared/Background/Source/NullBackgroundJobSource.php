<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Source;

use DateTimeImmutable;
use LogicException;
use Qmdb\Shared\Background\Job\BackgroundJobFailure;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;

final readonly class NullBackgroundJobSource implements BackgroundJobSource
{
    public function reserve(
        BackgroundWorkerIdentity $worker,
        DateTimeImmutable $now,
    ): ?ReservedBackgroundJob {
        return null;
    }

    public function acknowledge(ReservedBackgroundJob $job): void
    {
        throw new LogicException('The null job source cannot acknowledge a reservation.');
    }

    public function release(
        ReservedBackgroundJob $job,
        DateTimeImmutable $availableAt,
        BackgroundJobFailure $failure,
    ): void {
        throw new LogicException('The null job source cannot release a reservation.');
    }

    public function fail(ReservedBackgroundJob $job, BackgroundJobFailure $failure): void
    {
        throw new LogicException('The null job source cannot fail a reservation.');
    }
}
