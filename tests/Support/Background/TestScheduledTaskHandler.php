<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskHandler;
use Throwable;

final class TestScheduledTaskHandler implements ScheduledTaskHandler
{
    public int $calls = 0;
    public ?ScheduledTaskExecutionContext $context = null;

    public function __construct(private ?Throwable $failure = null, private mixed $return = null)
    {
    }

    public function __invoke(ScheduledTaskExecutionContext $context): mixed
    {
        ++$this->calls;
        $this->context = $context;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->return;
    }
}
