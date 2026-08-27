<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

enum AccountSecurityNotificationType: string
{
    case PASSWORD_RESET_COMPLETED = 'PASSWORD_RESET_COMPLETED';
}
