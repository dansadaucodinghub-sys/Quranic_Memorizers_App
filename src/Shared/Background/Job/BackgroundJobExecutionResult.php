<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

final readonly class BackgroundJobExecutionResult
{
    public function __construct(
        private BackgroundJobExecutionOutcome $outcome,
        private ?BackgroundJobFailure $failure = null,
    ) {
    }

    public function outcome(): BackgroundJobExecutionOutcome
    {
        return $this->outcome;
    }

    public function failure(): ?BackgroundJobFailure
    {
        return $this->failure;
    }
}
