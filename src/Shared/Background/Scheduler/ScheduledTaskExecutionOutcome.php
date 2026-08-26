<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

enum ScheduledTaskExecutionOutcome: string
{
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
}
