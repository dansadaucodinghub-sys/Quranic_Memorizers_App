<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

enum MigrationEventType: string
{
    case DISCOVERED = 'DISCOVERED';
    case STARTED = 'STARTED';
    case STEP_APPLIED = 'STEP_APPLIED';
    case PARTIAL = 'PARTIAL';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
    case ROLLBACK_STARTED = 'ROLLBACK_STARTED';
    case STEP_ROLLED_BACK = 'STEP_ROLLED_BACK';
    case ROLLED_BACK = 'ROLLED_BACK';
    case ROLLBACK_FAILED = 'ROLLBACK_FAILED';
    case DRIFT_DETECTED = 'DRIFT_DETECTED';
}
