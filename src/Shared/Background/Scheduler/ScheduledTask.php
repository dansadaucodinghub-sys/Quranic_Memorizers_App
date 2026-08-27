<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use InvalidArgumentException;

final readonly class ScheduledTask
{
    public function __construct(
        private ScheduledTaskId $id,
        private string $description,
        private Schedule $schedule,
        private ScheduledTaskHandler $handler,
        private int $leaseSeconds,
        private string $owningModule,
    ) {
        if (trim($description) === '' || strlen($description) > 160 || preg_match('/[\x00-\x1F\x7F]/', $description)) {
            throw new InvalidArgumentException('Scheduled task description is invalid.');
        }
        if ($leaseSeconds < 1 || $leaseSeconds > 86_400) {
            throw new InvalidArgumentException('Scheduled task lease is invalid.');
        }
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/D', $owningModule) !== 1) {
            throw new InvalidArgumentException('Scheduled task owning module is invalid.');
        }
    }

    public function id(): ScheduledTaskId
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schedule(): Schedule
    {
        return $this->schedule;
    }

    public function handler(): ScheduledTaskHandler
    {
        return $this->handler;
    }

    public function leaseSeconds(): int
    {
        return $this->leaseSeconds;
    }

    public function owningModule(): string
    {
        return $this->owningModule;
    }
}
