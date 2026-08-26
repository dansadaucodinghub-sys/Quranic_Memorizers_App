<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Configuration;

use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationViolation;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Throwable;

final readonly class BackgroundExecutionConfigurationFactory
{
    public function create(EnvironmentVariables $variables): BackgroundExecutionConfiguration
    {
        try {
            return new BackgroundExecutionConfiguration(
                $this->integer($variables, 'WORKER_MAX_JOBS', 100),
                $this->integer($variables, 'WORKER_MAX_RUNTIME_SECONDS', 300),
                $this->integer($variables, 'WORKER_IDLE_SLEEP_MS', 1_000),
                $this->integer($variables, 'WORKER_MAX_MEMORY_MB', 128),
                $variables->boolean('WORKER_REQUIRE_PCNTL_IN_PRODUCTION', true),
                $this->integer($variables, 'SCHEDULER_RUN_LEASE_SECONDS', 300),
                $this->integer($variables, 'SCHEDULER_LOCK_TIMEOUT_SECONDS', 1),
            );
        } catch (ConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ConfigurationException::fromViolation(new ConfigurationViolation(
                ConfigurationViolation::INVALID_BACKGROUND_CONFIGURATION,
                'BACKGROUND_EXECUTION',
                'Background execution configuration is invalid.',
            ));
        }
    }

    private function integer(EnvironmentVariables $variables, string $name, int $default): int
    {
        $value = $variables->optionalString($name);
        if ($value === null) {
            return $default;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($integer)) {
            throw ConfigurationException::fromViolation(new ConfigurationViolation(
                ConfigurationViolation::INVALID_BACKGROUND_CONFIGURATION,
                $name,
                'Background execution numeric setting is invalid.',
            ));
        }

        return $integer;
    }
}
