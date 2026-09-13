<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CompetitionP7LiveMaintenanceService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Closed P7 live-projection scheduler dispatch. */
final readonly class CompetitionP7LiveScheduledTask implements ScheduledTaskHandler
{
    public function __construct(private CompetitionP7LiveMaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'competition.live.project' => $this->maintenance->project(),
            'competition.live.reconcile' => $this->maintenance->reconcile(),
            'competition.live.outbox.retry' => $this->maintenance->retryExpiredClaims(),
            default => throw new \LogicException('Unregistered P7 live scheduler task cannot be dispatched.'),
        };

        return null;
    }
}
