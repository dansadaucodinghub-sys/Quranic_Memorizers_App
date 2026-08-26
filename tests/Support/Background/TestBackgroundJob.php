<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Background\Job\BackgroundJob;

final readonly class TestBackgroundJob implements BackgroundJob
{
    public function __construct(private string $payload = 'QMDB_TEST_JOB_PAYLOAD_SECRET')
    {
    }

    public function payload(): string
    {
        return $this->payload;
    }
}
