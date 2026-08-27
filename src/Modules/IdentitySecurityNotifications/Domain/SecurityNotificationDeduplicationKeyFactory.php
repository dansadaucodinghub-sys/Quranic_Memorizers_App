<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

final readonly class SecurityNotificationDeduplicationKeyFactory
{
    public function passwordResetCompleted(
        string $sourceChallengePublicId,
        string $accountPublicId,
    ): SecurityNotificationDeduplicationKey {
        $canonical = AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED->value
            . "\0" . $sourceChallengePublicId . "\0" . $accountPublicId;

        return new SecurityNotificationDeduplicationKey(hash('sha256', $canonical, true));
    }
}
