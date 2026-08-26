<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

enum ScheduledTaskClaimDisposition: string
{
    case CLAIMED = 'CLAIMED';
    case RECLAIMED = 'RECLAIMED';
    case SKIPPED_SUCCEEDED = 'SKIPPED_SUCCEEDED';
    case SKIPPED_FAILED = 'SKIPPED_FAILED';
    case SKIPPED_ACTIVE = 'SKIPPED_ACTIVE';
}
