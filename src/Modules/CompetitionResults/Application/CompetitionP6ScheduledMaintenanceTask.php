<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Application;

use Qmdb\Modules\CompetitionResults\Infrastructure\Persistence\CompetitionP6MaintenanceService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Dispatches only the closed set of registered P6 maintenance task identifiers. */
final readonly class CompetitionP6ScheduledMaintenanceTask implements ScheduledTaskHandler
{
    public function __construct(private CompetitionP6MaintenanceService $maintenance)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'competition.rounds.process' => $this->maintenance->processRounds(false),
            'competition.score_sheets.remind' => $this->maintenance->remindScoreSheets(false),
            'competition.appeal_windows.process' => $this->maintenance->processAppealWindows(false),
            'competition.score_sheets.reconcile' => $this->maintenance->reconcileScoreSheets(),
            'competition.results.reconcile' => $this->maintenance->reconcileResults(),
            default => throw new \LogicException('Unregistered P6 scheduler task cannot be dispatched.'),
        };

        return null;
    }
}
