<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

interface BackgroundJobContextResolver
{
    public function supports(BackgroundJob $job): bool;

    /** @throws PermanentBackgroundJobFailure */
    public function resolve(BackgroundJob $job): object;
}
