<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final class BackgroundConfigurationTest extends TestCase
{
    public function testDefaultsAreBoundedAndSafe(): void
    {
        $configuration = (new BackgroundExecutionConfigurationFactory())->create(new EnvironmentVariables([]));

        self::assertSame(100, $configuration->maximumJobs());
        self::assertSame(300, $configuration->maximumRuntimeSeconds());
        self::assertSame(1_000, $configuration->idleSleepMilliseconds());
        self::assertSame(128, $configuration->maximumMemoryMegabytes());
        self::assertTrue($configuration->requirePcntlInProduction());
        self::assertSame(300, $configuration->schedulerLeaseSeconds());
        self::assertSame(1, $configuration->schedulerLockTimeoutSeconds());
        self::assertArrayNotHasKey('reservation_token', $configuration->toSafeArray());
    }

    #[DataProvider('invalidConfigurations')]
    public function testUnsafeConfigurationIsRejected(string $name, string $value): void
    {
        $this->expectException(ConfigurationException::class);
        (new BackgroundExecutionConfigurationFactory())->create(new EnvironmentVariables([$name => $value]));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'zero jobs' => ['WORKER_MAX_JOBS', '0'];
        yield 'zero runtime' => ['WORKER_MAX_RUNTIME_SECONDS', '0'];
        yield 'negative idle' => ['WORKER_IDLE_SLEEP_MS', '-1'];
        yield 'excessive idle' => ['WORKER_IDLE_SLEEP_MS', '60001'];
        yield 'unsafe memory' => ['WORKER_MAX_MEMORY_MB', '1'];
        yield 'zero lease' => ['SCHEDULER_RUN_LEASE_SECONDS', '0'];
        yield 'negative lock timeout' => ['SCHEDULER_LOCK_TIMEOUT_SECONDS', '-1'];
        yield 'invalid boolean' => ['WORKER_REQUIRE_PCNTL_IN_PRODUCTION', 'perhaps'];
    }
}
