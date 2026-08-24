<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Shared\ExitCode;

final class ConsoleApplicationTest extends TestCase
{
    public function testAboutCommandSucceeds(): void
    {
        $result = $this->console()->run(['app:about'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
        self::assertSame('', $result->standardError());
    }

    public function testAboutCommandContainsOnlyRequiredSafeFields(): void
    {
        $result = $this->console()->run(['app:about'], '8.5.3', ['json', 'mbstring']);
        $output = $result->standardOutput();

        foreach (
            [
                'Qur’an Memorizer DB',
                'QMDB',
                'QMDB-P0-FRZ-001',
                'P1',
                'QMDB-P1-B01',
                '0.1.0-dev',
                '8.5.3',
                'Runtime Requirements: satisfied',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $output);
        }

        self::assertStringNotContainsString(__DIR__, $output);
        self::assertStringNotContainsString('password', strtolower($output));
    }

    public function testHelpCommandSucceeds(): void
    {
        $result = $this->console()->run(['help'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
        self::assertStringContainsString('Usage:', $result->standardOutput());
        self::assertStringContainsString('app:about', $result->standardOutput());
    }

    public function testLongHelpOptionSucceeds(): void
    {
        $result = $this->console()->run(['--help'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
    }

    public function testShortHelpOptionSucceeds(): void
    {
        $result = $this->console()->run(['-h'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
    }

    public function testUnknownCommandReturnsInvalidUsageOnStandardError(): void
    {
        $result = $this->console()->run(['unknown'], '8.5.0', ['json', 'mbstring']);

        self::assertSame(ExitCode::INVALID_USAGE, $result->exitCode());
        self::assertSame('', $result->standardOutput());
        self::assertStringContainsString('Unknown command.', $result->standardError());
        self::assertStringContainsString('help', $result->standardError());
    }

    public function testNoCommandLeaksAStackTrace(): void
    {
        $result = $this->console()->run(['unknown'], '8.5.0', ['json', 'mbstring']);

        self::assertStringNotContainsString('Stack trace', $result->standardError());
        self::assertStringNotContainsString(__DIR__, $result->standardError());
    }

    public function testRuntimeFailureProducesANonZeroSafeResult(): void
    {
        $result = $this->console()->run(['app:about'], '8.2.0', ['json']);

        self::assertSame(ExitCode::FAILURE, $result->exitCode());
        self::assertSame('', $result->standardOutput());
        self::assertStringContainsString('Runtime requirements are not satisfied', $result->standardError());
        self::assertStringNotContainsString(__DIR__, $result->standardError());
    }

    public function testAboutOutputIsDeterministicForASuppliedRuntime(): void
    {
        $first = $this->console()->run(['app:about'], '8.5.7', ['json', 'mbstring']);
        $second = $this->console()->run(['app:about'], '8.5.7', ['mbstring', 'json']);

        self::assertSame($first->standardOutput(), $second->standardOutput());
        self::assertSame($first->standardError(), $second->standardError());
    }

    private function console(): ConsoleApplication
    {
        return new ConsoleApplication(Application::bootstrap());
    }
}
