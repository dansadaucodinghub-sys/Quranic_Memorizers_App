<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

final readonly class NullWorkerSignalController implements WorkerSignalController
{
    public function isSupported(): bool
    {
        return false;
    }

    public function register(WorkerStopController $stopController): void
    {
    }

    public function restore(): void
    {
    }
}
