<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

final class WorkerStopController
{
    private ?BackgroundWorkerStopReason $reason = null;

    public function request(BackgroundWorkerStopReason $reason): void
    {
        $this->reason ??= $reason;
    }

    public function isRequested(): bool
    {
        return $this->reason !== null;
    }

    public function reason(): ?BackgroundWorkerStopReason
    {
        return $this->reason;
    }
}
