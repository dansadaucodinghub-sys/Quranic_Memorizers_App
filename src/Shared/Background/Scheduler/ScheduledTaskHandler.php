<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

interface ScheduledTaskHandler
{
    public function __invoke(ScheduledTaskExecutionContext $context): mixed;
}
