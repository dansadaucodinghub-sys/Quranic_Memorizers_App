<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Background\Worker\BackgroundWorkerStopReason;
use Qmdb\Shared\Background\Worker\WorkerSignalController;
use Qmdb\Shared\Background\Worker\WorkerStopController;

final class FakeSignalController implements WorkerSignalController
{
    public bool $registered = false;
    public bool $restored = false;
    private ?WorkerStopController $stop = null;

    public function __construct(private bool $supported = true, private bool $stopOnRegister = false)
    {
    }

    public function isSupported(): bool
    {
        return $this->supported;
    }

    public function register(WorkerStopController $stopController): void
    {
        $this->registered = true;
        $this->stop = $stopController;
        if ($this->stopOnRegister) {
            $this->requestStop();
        }
    }

    public function restore(): void
    {
        $this->restored = true;
    }

    public function requestStop(): void
    {
        $this->stop?->request(BackgroundWorkerStopReason::SIGNAL);
    }
}
