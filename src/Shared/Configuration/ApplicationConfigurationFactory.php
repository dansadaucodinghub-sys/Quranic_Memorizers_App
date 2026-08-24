<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

use DateTimeZone;
use InvalidArgumentException;
use LogicException;

final readonly class ApplicationConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        ConfigurationSource $source,
    ): ApplicationConfiguration {
        $violations = [];
        $environment = $this->environment($variables, $violations);
        $debugEnabled = $this->debugEnabled($variables, $violations);
        $timezone = $this->timezone($variables, $violations);

        if ($environment?->isProductionLike() === true && $debugEnabled === true) {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::DEBUG_PROHIBITED,
                'APP_DEBUG',
                'Debug mode is prohibited in staging and production.',
            );
        }

        if ($environment?->isProductionLike() === true && $source->includesLocalEnvironmentFile()) {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::DOTENV_PROHIBITED,
                'APP_ENV',
                'Local environment files are prohibited in staging and production.',
            );
        }

        if ($violations !== []) {
            throw new ConfigurationException($violations);
        }

        if ($environment === null) {
            throw new LogicException('Configuration validation did not resolve the application environment.');
        }

        return new ApplicationConfiguration(
            environment: $environment,
            debugEnabled: $debugEnabled,
            timezone: new DateTimeZone($timezone),
            source: $source,
        );
    }

    /**
     * @param list<ConfigurationViolation> $violations
     */
    private function environment(
        EnvironmentVariables $variables,
        array &$violations,
    ): ?ApplicationEnvironment {
        $value = $variables->optionalString('APP_ENV');

        if ($value === null || trim($value) === '') {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::REQUIRED_VALUE_MISSING,
                'APP_ENV',
                'APP_ENV is required.',
            );

            return null;
        }

        try {
            return ApplicationEnvironment::parse($value);
        } catch (InvalidArgumentException) {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::INVALID_ENVIRONMENT,
                'APP_ENV',
                'APP_ENV must be local, test, staging, or production.',
            );

            return null;
        }
    }

    /**
     * @param list<ConfigurationViolation> $violations
     */
    private function debugEnabled(EnvironmentVariables $variables, array &$violations): bool
    {
        try {
            return $variables->boolean('APP_DEBUG', false);
        } catch (InvalidArgumentException) {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::INVALID_BOOLEAN,
                'APP_DEBUG',
                'APP_DEBUG must be true, false, 1, or 0.',
            );

            return false;
        }
    }

    /**
     * @param list<ConfigurationViolation> $violations
     */
    private function timezone(EnvironmentVariables $variables, array &$violations): string
    {
        $timezone = trim($variables->optionalString('APP_TIMEZONE') ?? 'UTC');

        if ($timezone !== 'UTC') {
            $violations[] = new ConfigurationViolation(
                ConfigurationViolation::INVALID_TIMEZONE,
                'APP_TIMEZONE',
                'APP_TIMEZONE must be UTC.',
            );
        }

        return 'UTC';
    }
}
