<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Infrastructure;

use Dotenv\Dotenv;
use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\ConfigurationViolation;
use Qmdb\Shared\Configuration\EnvironmentLoader;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Configuration\LoadedEnvironment;
use Throwable;

final readonly class DotenvEnvironmentLoader implements EnvironmentLoader
{
    /** @param array<string, string>|null $processVariables */
    public function __construct(private ?array $processVariables = null)
    {
    }

    public function load(string $projectRoot): LoadedEnvironment
    {
        $processVariables = $this->processVariables ?? $this->readCurrentProcess();
        $processEnvironment = new EnvironmentVariables($processVariables);

        if ($this->isExternallyProductionLike($processEnvironment)) {
            return new LoadedEnvironment($processEnvironment, ConfigurationSource::PROCESS);
        }

        $dotenvPath = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($dotenvPath)) {
            return new LoadedEnvironment($processEnvironment, ConfigurationSource::PROCESS);
        }

        try {
            $contents = file_get_contents($dotenvPath);

            if ($contents === false) {
                throw new InvalidArgumentException('Environment file is unreadable.');
            }

            $fileVariables = $this->normalizeParsedVariables(Dotenv::parse($contents));
            $mergedVariables = array_replace($fileVariables, $processVariables);
            $variables = new EnvironmentVariables($mergedVariables);
        } catch (Throwable) {
            throw ConfigurationException::fromViolation(
                new ConfigurationViolation(
                    ConfigurationViolation::DOTENV_PARSE_FAILED,
                    'DOTENV',
                    'The local environment file could not be parsed safely.',
                ),
            );
        }

        $source = $processVariables === []
            ? ConfigurationSource::LOCAL_ENV_FILE
            : ConfigurationSource::PROCESS_AND_LOCAL_ENV_FILE;

        return new LoadedEnvironment($variables, $source);
    }

    /** @return array<string, string> */
    private function readCurrentProcess(): array
    {
        $nativeVariables = getenv();
        $variables = [];

        foreach ($nativeVariables as $name => $value) {
            if (
                preg_match('/\A[A-Z][A-Z0-9_]*\z/', $name) === 1
            ) {
                $variables[$name] = $value;
            }
        }

        return $variables;
    }

    private function isExternallyProductionLike(EnvironmentVariables $variables): bool
    {
        $value = $variables->optionalString('APP_ENV');

        if ($value === null || trim($value) === '') {
            return false;
        }

        try {
            return ApplicationEnvironment::parse($value)->isProductionLike();
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param array<string, string|null> $variables
     * @return array<string, string>
     */
    private function normalizeParsedVariables(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $name => $value) {
            $normalized[$name] = $value ?? '';
        }

        return $normalized;
    }
}
