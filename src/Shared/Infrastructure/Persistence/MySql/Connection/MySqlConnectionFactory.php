<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseConnectionException;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Throwable;

final readonly class MySqlConnectionFactory
{
    public function __construct(
        private DatabaseConfiguration $configuration,
        private MySqlDsnBuilder $dsnBuilder,
        private SecretsProvider $secrets,
        private PdoConnector $connector,
    ) {
    }

    public function create(): PDO
    {
        try {
            $password = $this->secrets->get(SecretName::fromString('DB_PASSWORD'));
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => $this->configuration->connectTimeoutSeconds(),
                \Pdo\Mysql::ATTR_MULTI_STATEMENTS => false,
            ];
            if ($this->configuration->tlsMode() === DatabaseTlsMode::VERIFY_SERVER) {
                $options[\Pdo\Mysql::ATTR_SSL_CA] = $this->configuration->tlsCaFile();
                $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            return $this->connector->connect(
                $this->dsnBuilder->build($this->configuration),
                $this->configuration->username(),
                $password->reveal(),
                $options,
            );
        } catch (Throwable $exception) {
            throw new DatabaseConnectionException($exception);
        }
    }
}
