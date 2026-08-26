<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Health;

use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Database\Health\DatabaseHealthReport;
use Qmdb\Shared\Database\Health\DatabaseHealthStatus;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseVerificationException;
use Throwable;

final readonly class MySqlDatabaseHealthCheck implements DatabaseHealthCheck
{
    public function __construct(private DatabaseConnectionProvider $connectionProvider)
    {
    }

    public function check(): DatabaseHealthReport
    {
        try {
            $connection = $this->connectionProvider->connection();
            if ($connection->query('SELECT 1') === false) {
                return new DatabaseHealthReport(DatabaseHealthStatus::UNAVAILABLE);
            }

            return new DatabaseHealthReport(DatabaseHealthStatus::READY);
        } catch (DatabaseVerificationException) {
            return new DatabaseHealthReport(DatabaseHealthStatus::INVALID_SESSION);
        } catch (Throwable) {
            return new DatabaseHealthReport(DatabaseHealthStatus::UNAVAILABLE);
        }
    }
}
