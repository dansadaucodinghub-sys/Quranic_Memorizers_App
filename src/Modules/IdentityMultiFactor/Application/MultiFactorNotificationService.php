<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;

final readonly class MultiFactorNotificationService
{
    public function __construct(
        private MultiFactorNotificationTargetRepository $targets,
        private AccountSecurityNotificationRepository $notifications,
        private SecurityNotificationDeduplicationKeyFactory $deduplicationKeys,
        private SecurityNotificationConfiguration $configuration,
    ) {
    }

    public function create(
        int $accountInternalId,
        AccountSecurityNotificationType $type,
        string $sourcePublicId,
        DateTimeImmutable $now,
    ): void {
        $target = $this->targets->notificationTarget($accountInternalId);
        if ($target === null) {
            throw new \UnexpectedValueException('Verified security-notification target is unavailable.');
        }
        $this->notifications->createPendingIntent(
            AccountSecurityNotificationId::generate(),
            $accountInternalId,
            $target['email_internal_id'],
            $type,
            $this->deduplicationKeys->forEvent($type, $sourcePublicId, $target['account_public_id']),
            $target['locale'],
            $this->configuration->maximumAttempts,
            $now,
        );
    }
}
