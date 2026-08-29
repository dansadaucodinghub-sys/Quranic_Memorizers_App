<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessEndService
{
    public function __construct(
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private SessionTenantContextRepository $tenantContexts,
        private PrivilegedAccessNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function end(AuthenticatedAccountContext $actor, CorrelationId $correlationId): ?PrivilegedAccessRequestSnapshot
    {
        return $this->transactions->transactional(function () use ($actor, $correlationId): ?PrivilegedAccessRequestSnapshot {
            $now = $this->clock->now();
            $state = $this->tenantContexts->state($actor, true);
            $ended = $this->lifecycle->endActive($actor, $correlationId->value(), $now);
            if ($ended === null) {
                return null;
            }
            if (!$this->tenantContexts->clear($actor, $state->version, $now)) {
                throw new \DomainException('The selected workspace context changed; retry from the current session state.');
            }
            $this->notifications->create(
                $actor->accountInternalId,
                match ($ended->type) {
                    PrivilegedAccessType::TEMPORARY_PRIVILEGE => AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_REVOKED,
                    PrivilegedAccessType::SUPPORT_ACCESS => AccountSecurityNotificationType::SUPPORT_ACCESS_ENDED,
                    PrivilegedAccessType::BREAK_GLASS => AccountSecurityNotificationType::BREAK_GLASS_ENDED,
                },
                $ended->id->toString(),
                $now,
            );

            return $ended;
        });
    }
}
