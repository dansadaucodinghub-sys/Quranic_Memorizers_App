<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use LogicException;

final class ScheduledTaskRegistry
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];
    private bool $frozen = false;

    public function register(ScheduledTask $task): self
    {
        if ($this->frozen) {
            throw new LogicException('Scheduled task registry is frozen.');
        }
        $this->tasks[] = $task;

        return $this;
    }

    public function build(): ScheduledTaskMap
    {
        $this->frozen = true;

        return new ScheduledTaskMap($this->tasks);
    }
}
