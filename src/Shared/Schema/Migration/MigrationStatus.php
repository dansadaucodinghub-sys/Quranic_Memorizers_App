<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

enum MigrationStatus: string
{
    case RUNNING = 'RUNNING';
    case PARTIAL = 'PARTIAL';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
    case ROLLING_BACK = 'ROLLING_BACK';
    case ROLLED_BACK = 'ROLLED_BACK';
    case ROLLBACK_FAILED = 'ROLLBACK_FAILED';
    case DRIFTED = 'DRIFTED';
}
