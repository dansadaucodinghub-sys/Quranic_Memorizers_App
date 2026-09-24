<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Application;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;

/** Bounded P9 task dispatch. Workers never approve or publish evidence. */
final readonly class MediaRuntimeScheduledTask implements ScheduledTaskHandler
{
    public function __construct(private MediaScanWorker $scans, private MediaProcessingWorker $processing, private MediaMaintenanceWorker $maintenance)
    {
    }
    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        $allowed = ['media.scans.process', 'media.processing.process', 'media.staging.cleanup', 'media.uploads.expire', 'media.assets.reconcile'];
        if (!in_array($context->taskId()->value(), $allowed, true)) {
            throw new \LogicException('Unregistered P9 scheduler task cannot be dispatched.');
        }
        if ($context->taskId()->value() === 'media.scans.process') {
            $this->scans->processOne();
            return null;
        }
        if ($context->taskId()->value() === 'media.processing.process') {
            $this->processing->processOne();
            return null;
        }
        $this->maintenance->run(str_replace('.', ':', substr($context->taskId()->value(), 6)));
        return null;
    }
}
