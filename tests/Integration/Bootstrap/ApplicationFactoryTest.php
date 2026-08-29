<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Bootstrap;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;
use ReflectionClass;

final class ApplicationFactoryTest extends TestCase
{
    public function testValidEnvironmentBuildsApplicationWithTypedConfiguration(): void
    {
        $application = $this->factory([
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
        ])->create('8.5.0', ['json', 'mbstring']);

        self::assertSame(ApplicationEnvironment::TEST, $application->configuration()->environment());
        self::assertSame('QMDB-P2-B08', $application->metadata()->currentBatch());
    }

    public function testInvalidEnvironmentFailsBeforeBootstrapExecution(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->factory(['APP_ENV' => 'unsafe-raw-value'])
            ->create('8.5.0', ['json', 'mbstring']);
    }

    public function testFactoryUsesTheSuppliedProjectRootForDotenvLoading(): void
    {
        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'qmdb-factory-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $path = $directory . DIRECTORY_SEPARATOR . '.env';
        self::assertNotFalse(file_put_contents($path, "APP_ENV=test\n"));

        try {
            $factory = new ApplicationFactory(
                projectRoot: $directory,
                environmentLoader: new DotenvEnvironmentLoader([]),
                configurationFactory: new ApplicationConfigurationFactory(),
            );
            $application = $factory->create('8.5.0', ['json', 'mbstring']);

            self::assertSame(ApplicationEnvironment::TEST, $application->configuration()->environment());
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function testFactoryConstructsExplicitFoundationAndIdentityModulesWithoutDirectDrivers(): void
    {
        $path = (new ReflectionClass(ApplicationFactory::class))->getFileName();
        self::assertIsString($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringContainsString('IdentityAccessModule', $source);
        self::assertStringContainsString('ApplicationHttpModule', $source);
        self::assertDoesNotMatchRegularExpression('/\b(?:mysqli|Redis)\b/', $source);
    }

    /** @param array<string, string> $variables */
    private function factory(array $variables): ApplicationFactory
    {
        return new ApplicationFactory(
            projectRoot: dirname(__DIR__, 3),
            environmentLoader: new DotenvEnvironmentLoader($variables),
            configurationFactory: new ApplicationConfigurationFactory(),
        );
    }
}
