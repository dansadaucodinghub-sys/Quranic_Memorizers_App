<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

interface MultiFactorNotificationTargetRepository
{
    /** @return array{email_internal_id: int, locale: string, account_public_id: string}|null */
    public function notificationTarget(int $accountInternalId): ?array;
}
