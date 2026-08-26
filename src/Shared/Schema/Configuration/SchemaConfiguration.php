<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Configuration;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseConfigurationException;

final readonly class SchemaConfiguration
{
    public function __construct(
        private DatabaseConfiguration $database,
        private int $lockTimeoutSeconds,
        private ApplicationEnvironment $environment,
    ) {
        if ($lockTimeoutSeconds < 1 || $lockTimeoutSeconds > 300) {
            throw new DatabaseConfigurationException(
                'SCHEMA_CONFIG_INVALID_LOCK_TIMEOUT',
                'Schema lock timeout is invalid.',
            );
        }
    }

    public function database(): DatabaseConfiguration
    {
        return $this->database;
    }

    public function lockTimeoutSeconds(): int
    {
        return $this->lockTimeoutSeconds;
    }

    public function environment(): ApplicationEnvironment
    {
        return $this->environment;
    }
}
