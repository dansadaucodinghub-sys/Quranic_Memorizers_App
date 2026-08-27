<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use InvalidArgumentException;

final readonly class ScheduledTaskRegistration
{
    public function __construct(
        public ScheduledTaskId $id,
        public string $description,
        public Schedule $schedule,
        public string $handlerServiceId,
        public int $leaseSeconds,
        public string $owningModule,
    ) {
        if ($handlerServiceId === '') {
            throw new InvalidArgumentException('Scheduled task handler service ID is required.');
        }
    }
}
