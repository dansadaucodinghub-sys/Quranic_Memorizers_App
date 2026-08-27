<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use Closure;
use UnexpectedValueException;

final readonly class DeferredScheduledTaskHandler implements ScheduledTaskHandler
{
    /** @param Closure(): object $resolver */
    public function __construct(
        private Closure $resolver,
        private string $serviceId,
    ) {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $handler = ($this->resolver)();
        if (!$handler instanceof ScheduledTaskHandler) {
            throw new UnexpectedValueException(sprintf(
                'Scheduled task handler service "%s" is invalid.',
                $this->serviceId,
            ));
        }

        return $handler($context);
    }
}
