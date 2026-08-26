<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

interface BackgroundJobHandler
{
    public function __invoke(BackgroundJob $job, BackgroundJobExecutionContext $context): mixed;
}
