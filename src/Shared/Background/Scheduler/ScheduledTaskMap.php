<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use InvalidArgumentException;

final readonly class ScheduledTaskMap
{
    /** @var array<string, ScheduledTask> */
    private array $tasks;

    /** @param list<ScheduledTask> $tasks */
    public function __construct(array $tasks)
    {
        $indexed = [];
        foreach ($tasks as $task) {
            $id = $task->id()->value();
            if (isset($indexed[$id])) {
                throw new InvalidArgumentException('Duplicate scheduled task ID.');
            }
            $indexed[$id] = $task;
        }
        ksort($indexed);
        $this->tasks = $indexed;
    }

    /** @return list<ScheduledTask> */
    public function tasks(): array
    {
        return array_values($this->tasks);
    }

    public function count(): int
    {
        return count($this->tasks);
    }
}
