<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Configuration\Logging;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Configuration\Logging\LoggingConfigurationFactory;
use Qmdb\Shared\Configuration\Logging\LogLevel;

final class LoggingConfigurationTest extends TestCase
{
    public function testDefaultConfigurationIsSafeAndFixed(): void
    {
        $configuration = $this->create([], ApplicationEnvironment::LOCAL);

        self::assertSame(LogLevel::INFO, $configuration->minimumLevel());
        self::assertSame([
            'level' => 'info',
            'output' => 'stderr',
            'format' => 'json',
            'timezone' => 'UTC',
        ], $configuration->toSafeArray());
        self::assertArrayNotHasKey('handler', $configuration->toSafeArray());
        self::assertArrayNotHasKey('path', $configuration->toSafeArray());
    }

    /** @return iterable<string, array{string, LogLevel}> */
    public static function acceptedLevels(): iterable
    {
        yield 'debug' => ['debug', LogLevel::DEBUG];
        yield 'info' => ['info', LogLevel::INFO];
        yield 'warning' => ['warning', LogLevel::WARNING];
        yield 'emergency' => ['emergency', LogLevel::EMERGENCY];
    }

    #[DataProvider('acceptedLevels')]
    public function testSupportedLevelsAreTyped(string $value, LogLevel $expected): void
    {
        self::assertSame($expected, $this->create(['APP_LOG_LEVEL' => $value])->minimumLevel());
    }

    public function testUnknownLevelIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->create(['APP_LOG_LEVEL' => 'verbose']);
    }

    #[DataProvider('productionLikeEnvironments')]
    public function testDebugIsRejectedInProductionLikeEnvironment(ApplicationEnvironment $environment): void
    {
        $this->expectException(ConfigurationException::class);
        $this->create(['APP_LOG_LEVEL' => 'debug'], $environment);
    }

    /** @return iterable<string, array{ApplicationEnvironment}> */
    public static function productionLikeEnvironments(): iterable
    {
        yield 'staging' => [ApplicationEnvironment::STAGING];
        yield 'production' => [ApplicationEnvironment::PRODUCTION];
    }

    /** @param array<string, string> $variables */
    private function create(
        array $variables,
        ApplicationEnvironment $environment = ApplicationEnvironment::LOCAL,
    ): \Qmdb\Shared\Configuration\Logging\LoggingConfiguration {
        return (new LoggingConfigurationFactory())->create(new EnvironmentVariables($variables), $environment);
    }
}
