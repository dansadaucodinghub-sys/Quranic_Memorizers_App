<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Health;

final readonly class DatabaseHealthReport
{
    public function __construct(private DatabaseHealthStatus $status)
    {
    }

    public function status(): DatabaseHealthStatus
    {
        return $this->status;
    }

    public function isReady(): bool
    {
        return $this->status === DatabaseHealthStatus::READY;
    }

    public function publicStatus(): string
    {
        return $this->isReady() ? 'ready' : 'not_ready';
    }
}
