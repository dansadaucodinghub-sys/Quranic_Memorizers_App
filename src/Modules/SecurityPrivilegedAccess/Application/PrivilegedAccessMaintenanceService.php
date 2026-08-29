<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessMaintenanceService
{
    public function __construct(
        private PrivilegedAccessMaintenanceRepository $repository,
        private PrivilegedAccessNotificationService $notifications,
        private PrivilegedAccessConfiguration $configuration,
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
            }

            return $result;
        });
    }
}
