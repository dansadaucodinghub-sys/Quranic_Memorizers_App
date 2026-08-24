<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Configuration;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationException;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\ConfigurationViolation;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;

final class DotenvEnvironmentLoaderTest extends TestCase
{
    public function testLocalEnvironmentFileLoadsWithoutMutatingTheProcess(): void
    {
        $this->withEnvironmentFile(
            "APP_ENV=local\nAPP_DEBUG=false\nAPP_TIMEZONE=UTC\n",
            function (string $directory): void {
                $loaded = (new DotenvEnvironmentLoader([]))->load($directory);

                self::assertSame('local', $loaded->variables()->requiredString('APP_ENV'));
                self::assertSame(ConfigurationSource::LOCAL_ENV_FILE, $loaded->source());
            },
        );
    }

    public function testTestEnvironmentFileLoads(): void
    {
        $this->withEnvironmentFile(
            "APP_ENV=test\n",
            function (string $directory): void {
                $loaded = (new DotenvEnvironmentLoader([]))->load($directory);

                self::assertSame('test', $loaded->variables()->requiredString('APP_ENV'));
            },
        );
    }

    public function testProcessValuesTakePrecedenceOverLocalFileValues(): void
    {
        $this->withEnvironmentFile(
            "APP_ENV=local\nAPP_DEBUG=true\nAPP_TIMEZONE=UTC\n",
            function (string $directory): void {
                $loaded = (new DotenvEnvironmentLoader([
                    'APP_DEBUG' => 'false',
                ]))->load($directory);

                self::assertSame('false', $loaded->variables()->requiredString('APP_DEBUG'));
                self::assertSame(
                    ConfigurationSource::PROCESS_AND_LOCAL_ENV_FILE,
                    $loaded->source(),
                );
            },
        );
    }

    public function testMissingFileWorksWithCompleteProcessConfiguration(): void
    {
        $this->withTemporaryDirectory(function (string $directory): void {
            $loaded = (new DotenvEnvironmentLoader([
                'APP_ENV' => 'local',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'UTC',
            ]))->load($directory);

            self::assertSame(ConfigurationSource::PROCESS, $loaded->source());
            self::assertSame('local', $loaded->variables()->requiredString('APP_ENV'));
        });
    }

    public function testMissingFileFailsOnlyWhenRequiredConfigurationIsAbsent(): void
    {
        $this->withTemporaryDirectory(function (string $directory): void {
            $loaded = (new DotenvEnvironmentLoader([]))->load($directory);

            $this->expectException(ConfigurationException::class);
            (new ApplicationConfigurationFactory())->create(
                $loaded->variables(),
                $loaded->source(),
            );
        });
    }

    public function testInvalidSyntaxFailsWithoutExposingFileContent(): void
    {
        $secret = 'QMDB_DOTENV_SECRET_d1190c';

        $this->withEnvironmentFile(
            'APP_ENV="unterminated-' . $secret,
            function (string $directory) use ($secret): void {
                try {
                    (new DotenvEnvironmentLoader([]))->load($directory);
                    self::fail('Invalid dotenv syntax must fail.');
                } catch (ConfigurationException $exception) {
                    self::assertSame(
                        ConfigurationViolation::DOTENV_PARSE_FAILED,
                        $exception->violations()[0]->code(),
                    );
                    self::assertStringNotContainsString($secret, $exception->getMessage());
                    self::assertStringNotContainsString($directory, $exception->getMessage());
                }
            },
        );
    }

    public function testExternallySuppliedProductionEnvironmentDoesNotReadLocalFile(): void
    {
        $this->withEnvironmentFile(
            'APP_ENV="invalid unterminated',
            function (string $directory): void {
                $loaded = (new DotenvEnvironmentLoader([
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => 'false',
                    'APP_TIMEZONE' => 'UTC',
                ]))->load($directory);

                self::assertSame(ConfigurationSource::PROCESS, $loaded->source());
                self::assertSame('production', $loaded->variables()->requiredString('APP_ENV'));
            },
        );
    }

    public function testFileResolvedProductionEnvironmentIsRejected(): void
    {
        $this->withEnvironmentFile(
            "APP_ENV=production\nAPP_DEBUG=false\nAPP_TIMEZONE=UTC\n",
            function (string $directory): void {
                $loaded = (new DotenvEnvironmentLoader([]))->load($directory);

                try {
                    (new ApplicationConfigurationFactory())->create(
                        $loaded->variables(),
                        $loaded->source(),
                    );
                    self::fail('Production dotenv configuration must fail.');
                } catch (ConfigurationException $exception) {
                    self::assertSame(
                        ConfigurationViolation::DOTENV_PROHIBITED,
                        $exception->violations()[0]->code(),
                    );
                }
            },
        );
    }

    /** @param callable(string): void $callback */
    private function withEnvironmentFile(string $contents, callable $callback): void
    {
        $this->withTemporaryDirectory(function (string $directory) use ($contents, $callback): void {
            $path = $directory . DIRECTORY_SEPARATOR . '.env';

            if (file_put_contents($path, $contents) === false) {
                self::fail('Unable to create isolated dotenv fixture.');
            }

            $callback($directory);
        });
    }

    /** @param callable(string): void $callback */
    private function withTemporaryDirectory(callable $callback): void
    {
        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'qmdb-p1-b02-'
            . bin2hex(random_bytes(8));

        if (!mkdir($directory, 0700)) {
            self::fail('Unable to create isolated test directory.');
        }

        try {
            $callback($directory);
        } finally {
            $dotenvPath = $directory . DIRECTORY_SEPARATOR . '.env';

            if (is_file($dotenvPath)) {
                unlink($dotenvPath);
            }

            rmdir($directory);
        }
    }
}
