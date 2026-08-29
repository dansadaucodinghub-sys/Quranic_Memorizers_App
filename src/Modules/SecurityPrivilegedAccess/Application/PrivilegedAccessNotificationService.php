<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;

/** Creates deduplicated, safe account-notification intents without exposing request content. */
final readonly class PrivilegedAccessNotificationService
{
    public function __construct(
        private MultiFactorNotificationTargetRepository $targets,
        private AccountSecurityNotificationRepository $notifications,
        private SecurityNotificationDeduplicationKeyFactory $deduplication,
        private SecurityNotificationConfiguration $configuration,
    ) {
    }

    public function create(
        int $accountInternalId,
        AccountSecurityNotificationType $type,
        string $safeSourcePublicId,
        DateTimeImmutable $now,
    ): void {
        $target = $this->targets->notificationTarget($accountInternalId);
        if ($target === null) {
            throw new \UnexpectedValueException('Verified security-notification target is unavailable.');
        }
        try {
            $this->notifications->createPendingIntent(
                AccountSecurityNotificationId::generate(),
                $accountInternalId,
                $target['email_internal_id'],
                $type,
                $this->deduplication->forEvent($type, $safeSourcePublicId, $target['account_public_id']),
                $target['locale'],
                $this->configuration->maximumAttempts,
                $now,
            );
        } catch (\Throwable $exception) {
            // The notification table has a unique deterministic deduplication key. A replay is safe.
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        }
    }
}
