<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Configuration;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationException;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;

final readonly class SchemaConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        DatabaseConfiguration $runtime,
        ApplicationEnvironment $environment,
    ): SchemaConfiguration {
        $username = $variables->requiredString('DB_SCHEMA_USERNAME');
        if ($username === $runtime->username()) {
            throw new DatabaseConfigurationException(
                'SCHEMA_CONFIG_CREDENTIAL_REUSE',
                'Runtime and schema database identities must be separate.',
            );
        }

        $timeout = $variables->optionalString('DB_SCHEMA_LOCK_TIMEOUT_SECONDS') ?? '30';
        $validatedTimeout = filter_var($timeout, FILTER_VALIDATE_INT);
        if (!is_int($validatedTimeout)) {
            throw new DatabaseConfigurationException(
                'SCHEMA_CONFIG_INVALID_LOCK_TIMEOUT',
                'Schema lock timeout is invalid.',
            );
        }

        return new SchemaConfiguration(
            new DatabaseConfiguration(
                $runtime->host(),
                $runtime->port(),
                $runtime->databaseName(),
                $username,
                DatabaseTlsMode::parse($runtime->tlsMode()->value),
                $runtime->tlsCaFile(),
                $runtime->connectTimeoutSeconds(),
                new TransactionRetryPolicy(1, 0, 0),
                $environment,
            ),
            $validatedTimeout,
            $environment,
        );
    }
}
