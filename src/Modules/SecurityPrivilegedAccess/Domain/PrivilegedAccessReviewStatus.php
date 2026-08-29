<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessReviewStatus: string
{
    case PENDING = 'PENDING';
    case OVERDUE = 'OVERDUE';
    case COMPLETED = 'COMPLETED';
}
