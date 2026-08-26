<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use InvalidArgumentException;
use LogicException;

final class BackgroundJobHandlerRegistry
{
    /** @var array<class-string<BackgroundJob>, BackgroundJobHandler> */
    private array $handlers = [];
    private bool $frozen = false;

    /** @param class-string<BackgroundJob> $jobClass */
    public function register(string $jobClass, BackgroundJobHandler $handler): self
    {
        if ($this->frozen) {
            throw new LogicException('Background job handler registry is frozen.');
        }
        if (!is_subclass_of($jobClass, BackgroundJob::class)) {
            throw new InvalidArgumentException('Registered job class must implement BackgroundJob.');
        }
        if (isset($this->handlers[$jobClass])) {
            throw new InvalidArgumentException('Duplicate background job handler registration.');
        }
        $this->handlers[$jobClass] = $handler;

        return $this;
    }

    public function build(): BackgroundJobHandlerMap
    {
        $this->frozen = true;

        return new BackgroundJobHandlerMap($this->handlers);
    }
}
