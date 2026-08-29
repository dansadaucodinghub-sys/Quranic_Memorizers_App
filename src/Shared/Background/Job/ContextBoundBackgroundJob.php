<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

/**
 * Marks jobs that require an independently revalidated execution context.
 */
interface ContextBoundBackgroundJob extends BackgroundJob
{
}
