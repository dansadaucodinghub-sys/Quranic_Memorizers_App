<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application\Mail;

final readonly class AccountSecurityNotificationMessage
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $textBody,
        public string $htmlBody,
    ) {
    }
}
