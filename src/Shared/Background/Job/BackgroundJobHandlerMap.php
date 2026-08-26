<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use InvalidArgumentException;

final readonly class BackgroundJobHandlerMap
{
    /** @var array<class-string<BackgroundJob>, BackgroundJobHandler> */
    private array $handlers;

    /** @param array<class-string<BackgroundJob>, BackgroundJobHandler> $handlers */
    public function __construct(array $handlers)
    {
        ksort($handlers);
        $this->handlers = $handlers;
    }

    public function handlerFor(BackgroundJob $job): BackgroundJobHandler
    {
        $class = $job::class;
        if (!isset($this->handlers[$class])) {
            throw new InvalidArgumentException('No exact background job handler is registered.');
        }

        return $this->handlers[$class];
    }

    public function count(): int
    {
        return count($this->handlers);
    }
}
