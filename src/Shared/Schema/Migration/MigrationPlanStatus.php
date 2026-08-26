<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

enum MigrationPlanStatus: string
{
    case PENDING = 'PENDING';
    case APPLIED = 'APPLIED';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';
    case ROLLED_BACK = 'ROLLED_BACK';
    case DRIFTED = 'DRIFTED';
    case BLOCKED_BY_DEPENDENCY = 'BLOCKED_BY_DEPENDENCY';
    case ORPHANED = 'ORPHANED_APPLIED_MIGRATION';
}
