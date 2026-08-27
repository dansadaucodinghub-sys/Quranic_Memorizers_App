<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application;

use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Throwable;

final readonly class AccountSecurityNotificationFailureClassifier
{
    /** @return array{retryable: bool, code: string} */
    public function classify(Throwable $failure): array
    {
        if ($failure instanceof EmailDeliveryException) {
            return ['retryable' => true, 'code' => 'MAIL_TRANSPORT'];
        }

        return ['retryable' => false, 'code' => 'DELIVERY_CONFIGURATION'];
    }
}
