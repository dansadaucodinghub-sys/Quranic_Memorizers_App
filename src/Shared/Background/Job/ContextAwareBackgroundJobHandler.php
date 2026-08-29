<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

interface ContextAwareBackgroundJobHandler extends BackgroundJobHandler
{
    public function __invokeWithContext(
        BackgroundJob $job,
        BackgroundJobExecutionContext $execution,
        object $resolvedContext,
    ): mixed;
}
