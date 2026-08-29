<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

enum PrivilegedAccessReviewOutcome: string
{
    case ACCEPTED_USE = 'ACCEPTED_USE';
    case CONCERN = 'CONCERN';
    case ESCALATED = 'ESCALATED';
}
