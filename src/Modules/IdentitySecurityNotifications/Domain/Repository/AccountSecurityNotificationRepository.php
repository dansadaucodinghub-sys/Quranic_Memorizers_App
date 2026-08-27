<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotification;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKey;

interface AccountSecurityNotificationRepository
{
    public function createPendingIntent(
        AccountSecurityNotificationId $id,
        int $accountInternalId,
        int $emailInternalId,
        AccountSecurityNotificationType $type,
        SecurityNotificationDeduplicationKey $deduplicationKey,
        string $locale,
        int $maximumAttempts,
        DateTimeImmutable $occurredAt,
    ): void;

    /** @return list<AccountSecurityNotification> */
    public function claimDue(
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        int $limit,
    ): array;

    public function verifiedRecipientCiphertext(AccountSecurityNotification $notification): ?string;

    public function markDelivered(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $now,
    ): bool;

    public function scheduleRetry(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        DateTimeImmutable $nextAttemptAt,
        string $failureCode,
        DateTimeImmutable $now,
    ): bool;

    public function markFailed(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        string $failureCode,
        DateTimeImmutable $now,
    ): bool;
}
