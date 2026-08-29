<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessPermissionPolicyStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
