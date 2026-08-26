<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Connection;

use PDO;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\PdoConnector;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseConnectionException;
use Qmdb\Shared\Schema\Configuration\SchemaConfiguration;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Throwable;

final class MySqlSchemaConnectionProvider implements SchemaConnectionProvider
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly SchemaConfiguration $configuration,
        private readonly MySqlDsnBuilder $dsnBuilder,
        private readonly SecretsProvider $secrets,
        private readonly PdoConnector $connector,
        private readonly MySqlSessionInitializer $initializer,
        private readonly MySqlSessionVerifier $sessionVerifier,
        private readonly MySqlServerVerifier $serverVerifier,
    ) {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $database = $this->configuration->database();
            $password = $this->secrets->get(SecretName::fromString('DB_SCHEMA_PASSWORD'));
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => $database->connectTimeoutSeconds(),
                \Pdo\Mysql::ATTR_MULTI_STATEMENTS => false,
            ];
            if ($database->tlsMode() === DatabaseTlsMode::VERIFY_SERVER) {
                $options[\Pdo\Mysql::ATTR_SSL_CA] = $database->tlsCaFile();
                $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            $connection = $this->connector->connect(
                $this->dsnBuilder->build($database),
                $database->username(),
                $password->reveal(),
                $options,
            );
            $this->initializer->initialize($connection);
            $this->sessionVerifier->verify($connection);
            $this->serverVerifier->verify($connection);
            $this->connection = $connection;

            return $connection;
        } catch (Throwable $exception) {
            throw new DatabaseConnectionException($exception);
        }
    }
}
