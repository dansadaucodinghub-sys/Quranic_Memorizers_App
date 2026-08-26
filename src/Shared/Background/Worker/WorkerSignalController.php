<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

interface WorkerSignalController
{
    public function isSupported(): bool;

    public function register(WorkerStopController $stopController): void;

    public function restore(): void;
}
