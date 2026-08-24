<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\ConfigurationViolation;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class ApplicationConfigurationFactoryTest extends TestCase
{
    public function testMinimalLocalConfigurationUsesSecureDefaults(): void
    {
        $configuration = $this->create(['APP_ENV' => 'local']);

        self::assertSame(ApplicationEnvironment::LOCAL, $configuration->environment());
        self::assertFalse($configuration->debugEnabled());
        self::assertSame('UTC', $configuration->timezone()->getName());
        self::assertSame(ConfigurationSource::PROCESS, $configuration->source());
    }

    public function testExplicitFalseDebugIsAccepted(): void
    {
        self::assertFalse($this->create(['APP_ENV' => 'test', 'APP_DEBUG' => 'false'])->debugEnabled());
    }

    public function testExplicitTrueDebugIsAllowedLocally(): void
    {
        self::assertTrue($this->create(['APP_ENV' => 'local', 'APP_DEBUG' => 'true'])->debugEnabled());
    }

    public function testDebugIsRejectedInStaging(): void
    {
        $exception = $this->failure(['APP_ENV' => 'staging', 'APP_DEBUG' => 'true']);

        self::assertSame(ConfigurationViolation::DEBUG_PROHIBITED, $exception->violations()[0]->code());
    }

    public function testDebugIsRejectedInProduction(): void
    {
        $exception = $this->failure(['APP_ENV' => 'production', 'APP_DEBUG' => '1']);

        self::assertSame(ConfigurationViolation::DEBUG_PROHIBITED, $exception->violations()[0]->code());
    }

    public function testInvalidEnvironmentIsRejectedWithoutRawValueLeakage(): void
    {
        $exception = $this->failure(['APP_ENV' => 'RAW_INVALID_ENVIRONMENT']);

        self::assertSame(ConfigurationViolation::INVALID_ENVIRONMENT, $exception->violations()[0]->code());
        self::assertStringNotContainsString('RAW_INVALID_ENVIRONMENT', $exception->getMessage());
    }

    public function testInvalidTimezoneIsRejected(): void
    {
        $exception = $this->failure(['APP_ENV' => 'local', 'APP_TIMEZONE' => 'Africa/Lagos']);

        self::assertSame(ConfigurationViolation::INVALID_TIMEZONE, $exception->violations()[0]->code());
        self::assertStringNotContainsString('Africa/Lagos', $exception->getMessage());
    }

    public function testMultipleViolationsAreAggregatedDeterministically(): void
    {
        $exception = $this->failure([
            'APP_ENV' => 'invalid-environment',
            'APP_DEBUG' => 'yes-secret',
            'APP_TIMEZONE' => 'Europe/Paris',
        ]);

        self::assertSame(
            [
                ConfigurationViolation::INVALID_ENVIRONMENT,
                ConfigurationViolation::INVALID_BOOLEAN,
                ConfigurationViolation::INVALID_TIMEZONE,
            ],
            array_map(
                static fn (ConfigurationViolation $violation): string => $violation->code(),
                $exception->violations(),
            ),
        );
        self::assertStringNotContainsString('invalid-environment', $exception->getMessage());
        self::assertStringNotContainsString('yes-secret', $exception->getMessage());
        self::assertStringNotContainsString('Europe/Paris', $exception->getMessage());
    }

    public function testProductionLikeDotenvSourceIsRejected(): void
    {
        $exception = $this->failure(
            ['APP_ENV' => 'production'],
            ConfigurationSource::LOCAL_ENV_FILE,
        );

        self::assertSame(ConfigurationViolation::DOTENV_PROHIBITED, $exception->violations()[0]->code());
    }

    public function testSafeArrayContainsOnlyApprovedNonSecretFields(): void
    {
        $configuration = $this->create(['APP_ENV' => 'test', 'UNUSED_TEST_SECRET' => 'do-not-expose']);

        self::assertSame(
            [
                'environment' => 'test',
                'debug_enabled' => false,
                'timezone' => 'UTC',
                'source' => 'process environment',
            ],
            $configuration->toSafeArray(),
        );
        self::assertStringNotContainsString(
            'do-not-expose',
            json_encode($configuration->toSafeArray(), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, string> $values
     */
    private function create(
        array $values,
        ConfigurationSource $source = ConfigurationSource::PROCESS,
    ): ApplicationConfiguration {
        return (new ApplicationConfigurationFactory())->create(
            new EnvironmentVariables($values),
            $source,
        );
    }

    /**
     * @param array<string, string> $values
     */
    private function failure(
        array $values,
        ConfigurationSource $source = ConfigurationSource::PROCESS,
    ): ConfigurationException {
        try {
            $this->create($values, $source);
            self::fail('Invalid configuration must fail.');
        } catch (ConfigurationException $exception) {
            return $exception;
        }
    }
}
