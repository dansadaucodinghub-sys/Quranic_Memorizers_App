<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

enum AccountSecurityNotificationEventType: string
{
    case CREATED = 'CREATED';
    case CLAIMED = 'CLAIMED';
    case DELIVERED = 'DELIVERED';
    case RETRY_SCHEDULED = 'RETRY_SCHEDULED';
    case FAILED = 'FAILED';
    case SUPPRESSED = 'SUPPRESSED';
}
