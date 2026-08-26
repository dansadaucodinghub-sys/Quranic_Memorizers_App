<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

enum ScheduledTaskRunStatus: string
{
    case CLAIMED = 'CLAIMED';
    case RUNNING = 'RUNNING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case ABANDONED = 'ABANDONED';
}
