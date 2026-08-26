<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

enum BackgroundWorkerStopReason: string
{
    case NO_WORK_ONCE = 'NO_WORK_ONCE';
    case MAX_JOBS = 'MAX_JOBS';
    case MAX_RUNTIME = 'MAX_RUNTIME';
    case MAX_MEMORY = 'MAX_MEMORY';
    case SIGNAL = 'SIGNAL';
    case SOURCE_FAILURE = 'SOURCE_FAILURE';
    case EXECUTION_FAILURE = 'EXECUTION_FAILURE';
    case CONFIGURATION_FAILURE = 'CONFIGURATION_FAILURE';
}
