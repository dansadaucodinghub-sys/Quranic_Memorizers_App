<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

use DateTimeImmutable;
use DomainException;

final readonly class AccountSecurityNotification
{
    public function __construct(
        public int $internalId,
        public AccountSecurityNotificationId $id,
        public int $accountInternalId,
        public int $emailInternalId,
        public AccountSecurityNotificationType $type,
        public SecurityNotificationDeduplicationKey $deduplicationKey,
        public string $locale,
        public AccountSecurityNotificationStatus $status,
        public int $attemptCount,
        public int $maximumAttempts,
        public DateTimeImmutable $nextAttemptAt,
        public ?NotificationClaimExecutionId $claimExecutionId,
        public ?DateTimeImmutable $leaseExpiresAt,
        public int $version,
        public DateTimeImmutable $occurredAt,
    ) {
        if (!in_array($locale, ['en', 'ar'], true)) {
            throw new DomainException('Security notification locale is invalid.');
        }
        if ($attemptCount < 0 || $maximumAttempts < 1 || $attemptCount > $maximumAttempts || $version < 1) {
            throw new DomainException('Security notification counters are invalid.');
        }
        if ($status === AccountSecurityNotificationStatus::CLAIMED && $claimExecutionId === null) {
            throw new DomainException('Claimed security notification requires an execution ID.');
        }
    }
}
