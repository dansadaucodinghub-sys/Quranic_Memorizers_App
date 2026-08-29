<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessRequestCancellationService
{
    public function __construct(
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function cancel(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        CorrelationId $correlationId,
    ): PrivilegedAccessRequestSnapshot {
        return $this->transactions->transactional(fn (): PrivilegedAccessRequestSnapshot => $this->lifecycle->cancel(
            $actor,
            $requestId,
            $correlationId->value(),
            $this->clock->now(),
        ));
    }
}
