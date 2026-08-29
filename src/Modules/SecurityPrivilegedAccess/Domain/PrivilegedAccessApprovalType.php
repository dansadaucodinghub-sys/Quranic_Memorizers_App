<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessApprovalType: string
{
    case PLATFORM = 'PLATFORM';
    case WORKSPACE = 'WORKSPACE';
}
