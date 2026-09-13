<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Application;

use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence\CompetitionAppealMaintenanceService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Read-only bounded appeal integrity processor. */
final readonly class CompetitionAppealMaintenanceScheduledTask implements ScheduledTaskHandler
{
    public function __construct(private CompetitionAppealMaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        if ($context->taskId()->value() !== 'competition.appeals.process') {
            throw new \LogicException('Unregistered appeal maintenance scheduler task cannot be dispatched.');
        }
        $this->maintenance->process();

        return null;
    }
}
