<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

enum AccountSecurityNotificationStatus: string
{
    case PENDING = 'PENDING';
    case CLAIMED = 'CLAIMED';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case SUPPRESSED = 'SUPPRESSED';
}
