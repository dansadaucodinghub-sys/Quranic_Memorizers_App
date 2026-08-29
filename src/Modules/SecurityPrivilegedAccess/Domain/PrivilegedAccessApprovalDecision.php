<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessApprovalDecision: string
{
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
