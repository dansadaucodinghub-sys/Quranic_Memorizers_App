<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final class MySqlConnectionProvider implements DatabaseConnectionProvider
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly MySqlConnectionFactory $factory,
        private readonly MySqlSessionInitializer $initializer,
        private readonly MySqlSessionVerifier $sessionVerifier,
        private readonly MySqlServerVerifier $serverVerifier,
    ) {
    }

    public function connection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $connection = $this->factory->create();
        $this->initializer->initialize($connection);
        $this->sessionVerifier->verify($connection);
        $this->serverVerifier->verify($connection);
        $this->connection = $connection;

        return $this->connection;
    }
}
