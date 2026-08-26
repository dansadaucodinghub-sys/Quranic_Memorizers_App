<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

enum SeedEventType: string
{
    case DISCOVERED = 'DISCOVERED';
    case STARTED = 'STARTED';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
    case DRIFT_DETECTED = 'DRIFT_DETECTED';
}
