<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

enum BackgroundJobExecutionOutcome: string
{
    case SUCCEEDED = 'SUCCEEDED';
    case RETRY_SCHEDULED = 'RETRY_SCHEDULED';
    case FAILED = 'FAILED';
}
