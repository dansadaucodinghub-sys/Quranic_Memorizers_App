<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Lock;

use PDO;
use Qmdb\Shared\Schema\Configuration\SchemaConfiguration;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Throwable;

final readonly class SchemaLockManager implements SchemaMutationLock
{
    public function __construct(
        private SchemaConnectionProvider $provider,
        private SchemaConfiguration $configuration,
    ) {
    }

    public function acquire(): SchemaLockHandle
    {
        $name = $this->lockName();
        try {
            $statement = $this->provider->connection()->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds)');
            $statement->bindValue(':lock_name', $name, PDO::PARAM_STR);
            $statement->bindValue(':timeout_seconds', $this->configuration->lockTimeoutSeconds(), PDO::PARAM_INT);
            $statement->execute();
            $result = $statement->fetchColumn();
        } catch (Throwable $exception) {
            throw new SchemaException('SCHEMA_LOCK_ERROR', 'Schema lock could not be acquired.', $exception);
        }
        if ((int) $result !== 1) {
            throw new SchemaException('SCHEMA_LOCK_TIMEOUT', 'Schema lock acquisition timed out.');
        }

        return new SchemaLockHandle($name);
    }

    public function release(SchemaLockHandle $handle): void
    {
        if ($handle->isReleased()) {
            throw new SchemaException('SCHEMA_LOCK_ALREADY_RELEASED', 'Schema lock was already released.');
        }
        try {
            $statement = $this->provider->connection()->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute([':lock_name' => $handle->name()]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new SchemaException('SCHEMA_LOCK_RELEASE_FAILED', 'Schema lock could not be released.');
            }
            $handle->markReleased();
        } catch (SchemaException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SchemaException('SCHEMA_LOCK_RELEASE_FAILED', 'Schema lock could not be released.', $exception);
        }
    }

    public function lockName(): string
    {
        $database = $this->configuration->database();
        $identity = $database->host() . ':' . $database->port() . '/' . $database->databaseName();

        return 'qmdb:schema:' . substr(hash('sha256', 'schema-ledger|' . $identity), 0, 40);
    }
}
