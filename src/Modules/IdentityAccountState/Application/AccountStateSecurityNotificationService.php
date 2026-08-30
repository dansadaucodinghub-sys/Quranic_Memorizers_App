<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;

final readonly class AccountStateSecurityNotificationService
{
    public function __construct(
        private MultiFactorNotificationTargetRepository $targets,
        private AccountSecurityNotificationRepository $notifications,
        private SecurityNotificationDeduplicationKeyFactory $deduplication,
        private SecurityNotificationConfiguration $configuration,
    ) {
    }

    public function create(int $accountId, AccountSecurityNotificationType $type, string $operationPublicId, DateTimeImmutable $now): void
    {
        $target = $this->targets->notificationTarget($accountId);
        if ($target === null) {
            throw new \UnexpectedValueException('Verified security-notification target is unavailable.');
        }
        $this->notifications->createPendingIntent(
            AccountSecurityNotificationId::generate(),
            $accountId,
            $target['email_internal_id'],
            $type,
            $this->deduplication->forEvent($type, $operationPublicId, $target['account_public_id']),
            $target['locale'],
            $this->configuration->maximumAttempts,
            $now,
        );
    }
}
