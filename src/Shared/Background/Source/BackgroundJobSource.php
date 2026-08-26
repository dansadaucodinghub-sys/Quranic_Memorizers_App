<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Source;

use DateTimeImmutable;
use Qmdb\Shared\Background\Job\BackgroundJobFailure;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;

interface BackgroundJobSource
{
    public function reserve(
        BackgroundWorkerIdentity $worker,
        DateTimeImmutable $now,
    ): ?ReservedBackgroundJob;

    public function acknowledge(ReservedBackgroundJob $job): void;

    public function release(
        ReservedBackgroundJob $job,
        DateTimeImmutable $availableAt,
        BackgroundJobFailure $failure,
    ): void;

    public function fail(ReservedBackgroundJob $job, BackgroundJobFailure $failure): void;
}
