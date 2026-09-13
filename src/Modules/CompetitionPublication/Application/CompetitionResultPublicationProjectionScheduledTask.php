<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Application;

use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\CompetitionResultPublicationProjectionService;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Scheduler adapter for bounded publication projection processing only. */
final readonly class CompetitionResultPublicationProjectionScheduledTask implements ScheduledTaskHandler
{
    public function __construct(private CompetitionResultPublicationProjectionService $projections)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        match ($context->taskId()->value()) {
            'competition.result_publications.process' => $this->projections->process(),
            'competition.result_publications.reconcile' => $this->projections->reconcile(),
            default => throw new \LogicException('Unregistered publication projection scheduler task cannot be dispatched.'),
        };

        return null;
    }
}
