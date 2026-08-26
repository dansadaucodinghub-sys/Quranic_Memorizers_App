<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Database;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Throwable;

final readonly class DatabaseConfigurationFactory
{
    public function create(EnvironmentVariables $variables, ApplicationEnvironment $environment): DatabaseConfiguration
    {
        try {
            return new DatabaseConfiguration(
                host: $variables->optionalString('DB_HOST') ?? '127.0.0.1',
                port: $this->integer($variables, 'DB_PORT', 3_306),
                databaseName: $variables->optionalString('DB_NAME') ?? 'qmdb',
                username: $variables->optionalString('DB_USERNAME') ?? 'qmdb_app',
                tlsMode: DatabaseTlsMode::parse($variables->optionalString('DB_TLS_MODE') ?? 'disabled'),
                tlsCaFile: $this->optionalTrimmed($variables, 'DB_TLS_CA_FILE'),
                connectTimeoutSeconds: $this->integer($variables, 'DB_CONNECT_TIMEOUT_SECONDS', 5),
                deadlockRetryPolicy: new TransactionRetryPolicy(
                    $this->integer($variables, 'DB_DEADLOCK_MAX_ATTEMPTS', 3),
                    $this->integer($variables, 'DB_DEADLOCK_BASE_DELAY_MS', 25),
                    $this->integer($variables, 'DB_DEADLOCK_MAX_DELAY_MS', 250),
                ),
                environment: $environment,
            );
        } catch (DatabaseConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_INVALID_VALUE',
                'Database configuration contains an invalid or missing value.',
            );
        }
    }

    private function integer(EnvironmentVariables $variables, string $name, int $default): int
    {
        $value = $variables->optionalString($name);
        if ($value === null) {
            return $default;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($validated)) {
            throw new DatabaseConfigurationException(
                'DB_CONFIG_INVALID_INTEGER',
                'Database numeric setting is invalid.',
            );
        }

        return $validated;
    }

    private function optionalTrimmed(EnvironmentVariables $variables, string $name): ?string
    {
        $value = $variables->optionalString($name);
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
