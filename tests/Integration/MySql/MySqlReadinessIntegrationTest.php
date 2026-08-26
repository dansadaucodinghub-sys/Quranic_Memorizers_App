<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use Qmdb\Shared\Database\Health\DatabaseHealthStatus;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Health\MySqlDatabaseHealthCheck;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class MySqlReadinessIntegrationTest extends MySqlIntegrationTestCase
{
    public function testHealthyAndUnavailableDatabaseProduceBinaryHealth(): void
    {
        $healthy = (new MySqlDatabaseHealthCheck($this->provider()))->check();
        $unavailable = (new MySqlDatabaseHealthCheck(
            $this->provider($this->configuration(['port' => 1])),
        ))->check();

        self::assertSame(DatabaseHealthStatus::READY, $healthy->status());
        self::assertSame('ready', $healthy->publicStatus());
        self::assertSame(DatabaseHealthStatus::UNAVAILABLE, $unavailable->status());
        self::assertSame('not_ready', $unavailable->publicStatus());
    }
}
