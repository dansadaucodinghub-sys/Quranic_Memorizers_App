<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Health;

final readonly class SchemaHealthReport
{
    public function __construct(public SchemaHealthStatus $status)
    {
    }

    public function isReady(): bool
    {
        return $this->status === SchemaHealthStatus::READY;
    }

    public function publicStatus(): string
    {
        return $this->isReady() ? 'ready' : 'not_ready';
    }
}
