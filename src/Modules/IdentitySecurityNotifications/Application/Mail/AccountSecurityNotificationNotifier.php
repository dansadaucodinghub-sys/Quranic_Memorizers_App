<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application\Mail;

interface AccountSecurityNotificationNotifier
{
    public function send(AccountSecurityNotificationMessage $message): void;
}
