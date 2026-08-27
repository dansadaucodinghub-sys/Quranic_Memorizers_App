<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationEventType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;

interface AccountSecurityNotificationEventRepository
{
    public function appendEvent(
        int $notificationInternalId,
        AccountSecurityNotificationEventType $type,
        DateTimeImmutable $occurredAt,
        ?int $attemptNumber = null,
        ?NotificationClaimExecutionId $executionId = null,
        ?string $failureCode = null,
    ): void;
}
