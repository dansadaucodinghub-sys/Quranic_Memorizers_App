<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;

final class BackgroundConsoleIntegrationTest extends TestCase
{
    /** @param list<string> $arguments */
    #[DataProvider('successfulCommands')]
    public function testComposedBackgroundCommandsExecuteSafely(array $arguments, string $expected): void
    {
        $result = $this->console()->run($arguments, '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
        self::assertSame('', $result->standardError());
        self::assertStringContainsString($expected, $result->standardOutput());
        self::assertStringNotContainsString('reservation', strtolower($result->standardOutput()));
        self::assertStringNotContainsString('handler', strtolower($result->standardOutput()));
    }

    /** @return iterable<string, array{list<string>, string}> */
    public static function successfulCommands(): iterable
    {
        yield 'help' => [['help'], 'schedule:list'];
        yield 'about' => [['app:about'], 'QMDB-P3-B06'];
        yield 'schedule list' => [['schedule:list'], 'identity.security_notifications.deliver'];
        yield 'worker once' => [['worker:run', '--once'], 'NO_WORK_ONCE'];
        yield 'bounded worker' => [[
            'worker:run',
            '--max-jobs=1',
            '--max-runtime-seconds=1',
            '--idle-sleep-ms=1000',
            '--max-memory-mb=65536',
        ], 'MAX_RUNTIME'];
    }

    public function testScheduleRunFailsClosedWhenTheDatabaseIsUnavailable(): void
    {
        $result = $this->console()->run(['schedule:run'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::FAILURE, $result->exitCode());
        self::assertStringContainsString('Failed: 16', $result->standardOutput());
        self::assertStringNotContainsString('password', strtolower($result->standardError()));
    }

    /** @param list<string> $arguments */
    #[DataProvider('invalidWorkerArguments')]
    public function testInvalidWorkerOptionsFailSafelyWithoutEchoingValues(array $arguments): void
    {
        $secret = 'QMDB_OPTION_SECRET_127';
        $arguments = array_map(
            static fn (string $value): string => str_replace('{secret}', $secret, $value),
            $arguments,
        );
        $result = $this->console()->run($arguments, '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::INVALID_USAGE, $result->exitCode());
        self::assertSame('', $result->standardOutput());
        self::assertStringNotContainsString($secret, $result->standardError());
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidWorkerArguments(): iterable
    {
        yield 'unknown option' => [['worker:run', '--queue={secret}']];
        yield 'class selector' => [['worker:run', '--job-class={secret}']];
        yield 'secret option' => [['worker:run', '--password={secret}']];
        yield 'duplicate option' => [['worker:run', '--max-jobs=2', '--max-jobs=3']];
        yield 'invalid bound' => [['worker:run', '--max-jobs=0']];
    }

    public function testUnknownCommandCannotSelectAService(): void
    {
        $result = $this->console()->run(['service:Qmdb\\Secret'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::INVALID_USAGE, $result->exitCode());
        self::assertStringContainsString('invalid', strtolower($result->standardError()));
    }

    private function console(): ConsoleApplication
    {
        return (new ApplicationFactory(
            dirname(__DIR__, 3),
            new DotenvEnvironmentLoader($this->testEnvironment()),
            new ApplicationConfigurationFactory(),
        ))->createConsoleApplication('8.5.0', ['json', 'mbstring']);
    }

    /** @return array<string, string> */
    private function testEnvironment(): array
    {
        return [
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'UTC',
            'AUTH_CSRF_SIGNING_KEY' => str_repeat('c', 64),
            'AUTH_IDENTITY_HMAC_KEY' => str_repeat('i', 64),
            'AUTH_CONTACT_ENCRYPTION_KEY' => base64_encode(str_repeat('k', 32)),
            'AUTH_MFA_ENCRYPTION_KEY' => base64_encode(str_repeat('m', 32)),
            'AUTH_SECURITY_AUDIT_HMAC_KEY' => str_repeat('a', 64),
            'MAILER_DSN' => 'null://null',
        ];
    }
}
