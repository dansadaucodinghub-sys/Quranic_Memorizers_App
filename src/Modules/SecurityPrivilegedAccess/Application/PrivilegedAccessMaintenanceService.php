<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessMaintenanceService
{
    public function __construct(
        private PrivilegedAccessMaintenanceRepository $repository,
        private PrivilegedAccessNotificationService $notifications,
        private PrivilegedAccessConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function maintain(?DateTimeImmutable $now = null): PrivilegedAccessMaintenanceResult
    {
        $now ??= $this->clock->now();

        return $this->transactions->transactional(function () use ($now): PrivilegedAccessMaintenanceResult {
            $result = $this->repository->maintain(
                $now,
                $this->configuration->maintenanceBatchSize,
                $this->configuration->reviewTtlSeconds,
            );
            foreach ($result->notifications as $notification) {
                $this->notifications->create(
                    $notification->accountInternalId,
                    $notification->type,
                    $notification->requestPublicId,
                    $now,
                );
                $event = match ($notification->type) {
                    AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_EXPIRED => SecurityEventCode::TEMPORARY_EXPIRED,
                    AccountSecurityNotificationType::SUPPORT_ACCESS_ENDED => SecurityEventCode::SUPPORT_EXPIRED,
                    AccountSecurityNotificationType::BREAK_GLASS_EXPIRED => SecurityEventCode::BREAK_GLASS_EXPIRED,
                    default => null,
                };
                if ($event !== null) {
                    $this->audit->privilegedAccess(
                        $event,
                        $notification->scope->value === 'WORKSPACE',
                        $notification->workspacePublicId,
                        SecurityEventSubjectKind::PRIVILEGED_ACCESS,
                        $notification->requestPublicId,
                        null,
                        $now,
                    );
                }
            }

            return $result;
        });
    }
}
