<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Logging;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationViolation;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use InvalidArgumentException;

final readonly class LoggingConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        ApplicationEnvironment $environment,
    ): LoggingConfiguration {
        try {
            $level = LogLevel::parse($variables->optionalString('APP_LOG_LEVEL') ?? LogLevel::INFO->value);
        } catch (InvalidArgumentException) {
            throw new ConfigurationException([
                new ConfigurationViolation(
                    ConfigurationViolation::INVALID_LOG_LEVEL,
                    'APP_LOG_LEVEL',
                    'APP_LOG_LEVEL must be a supported logging level.',
                ),
            ]);
        }

        if ($environment->isProductionLike() && $level === LogLevel::DEBUG) {
            throw new ConfigurationException([
                new ConfigurationViolation(
                    ConfigurationViolation::INVALID_LOG_LEVEL,
                    'APP_LOG_LEVEL',
                    'Debug logging is prohibited in staging and production.',
                ),
            ]);
        }

        return new LoggingConfiguration($level);
    }
}
