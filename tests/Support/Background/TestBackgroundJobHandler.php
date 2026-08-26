<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Closure;
use Qmdb\Shared\Background\Job\BackgroundJob;
use Qmdb\Shared\Background\Job\BackgroundJobExecutionContext;
use Qmdb\Shared\Background\Job\BackgroundJobHandler;
use Throwable;

final class TestBackgroundJobHandler implements BackgroundJobHandler
{
    public int $calls = 0;
    public ?BackgroundJob $job = null;
    public ?BackgroundJobExecutionContext $context = null;

    public function __construct(
        private ?Throwable $failure = null,
        private mixed $return = null,
        private ?Closure $duringExecution = null,
    ) {
    }

    public function __invoke(BackgroundJob $job, BackgroundJobExecutionContext $context): mixed
    {
        ++$this->calls;
        $this->job = $job;
        $this->context = $context;
        if ($this->duringExecution !== null) {
            ($this->duringExecution)();
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->return;
    }
}
