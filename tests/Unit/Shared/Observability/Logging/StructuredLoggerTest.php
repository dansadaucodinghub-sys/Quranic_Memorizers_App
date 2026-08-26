<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Observability\Logging;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\Logging\LoggingConfiguration;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Error\InternalErrorChannel;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Observability\Logging\MonologStructuredLoggerFactory;
use Qmdb\Shared\Observability\Logging\PsrEventLogger;
use Qmdb\Shared\Observability\Logging\ResilientLogger;
use Qmdb\Shared\Observability\Logging\SensitiveKeyMatcher;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Qmdb\Shared\Observability\Logging\StaticApplicationContextProcessor;
use Qmdb\Tests\Support\Observability\FailingPsrLogger;
use Qmdb\Tests\Support\Observability\RecordingPsrLogger;

final class StructuredLoggerTest extends TestCase
{
    public function testJsonLoggerProducesOneUtcRedactedMachineReadableLine(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $redactor = new SensitiveValueRedactor(new SensitiveKeyMatcher());
        $sanitizer = new LogContextSanitizer();
        $logger = (new MonologStructuredLoggerFactory(
            new LoggingConfiguration(LogLevel::INFO),
            new StaticApplicationContextProcessor([
                'application' => 'QMDB',
                'environment' => 'test',
                'phase' => 'P1',
                'batch' => 'QMDB-P1-B07',
                'baseline' => 'QMDB-P0-FRZ-001',
            ]),
            $stream,
        ))->create();
        $events = new PsrEventLogger($logger, $redactor, $sanitizer);
        $secret = 'QMDB_LOG_SECRET_8317';

        $events->log(LogLevel::INFO, new LogEventName('http.request.completed'), [
            'request_id' => str_repeat('a', 32),
            'password' => $secret,
            'object' => new \stdClass(),
        ]);
        rewind($stream);
        $line = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($line);
        $lines = array_values(array_filter(explode("\n", $line), static fn (string $value): bool => $value !== ''));

        self::assertCount(1, $lines);
        $record = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertIsArray($record['context']);
        self::assertIsArray($record['extra']);
        self::assertIsString($record['datetime']);
        self::assertSame('http.request.completed', $record['message']);
        self::assertSame('INFO', $record['level_name']);
        self::assertSame('qmdb', $record['channel']);
        self::assertSame(SensitiveValueRedactor::REDACTED, $record['context']['password']);
        self::assertSame('[object:stdClass]', $record['context']['object']);
        self::assertSame('QMDB-P1-B07', $record['extra']['batch']);
        self::assertMatchesRegularExpression('/(?:Z|\+00:00)$/', $record['datetime']);
        self::assertStringNotContainsString($secret, $line);
    }

    public function testEventLoggerUsesTypedLevelMessageAndSanitizedContext(): void
    {
        $psr = new RecordingPsrLogger();
        $events = new PsrEventLogger(
            $psr,
            new SensitiveValueRedactor(new SensitiveKeyMatcher()),
            new LogContextSanitizer(),
        );
        $events->log(LogLevel::WARNING, new LogEventName('schema.migration.failed'), [
            'authorization' => 'Bearer QMDB_SECRET',
        ]);

        self::assertSame('warning', $psr->records()[0]['level']);
        self::assertSame('schema.migration.failed', $psr->records()[0]['message']);
        self::assertSame('[REDACTED]', $psr->records()[0]['context']['authorization']);
    }

    public function testMinimumLevelSuppressesLowerSeverity(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        $logger = (new MonologStructuredLoggerFactory(
            new LoggingConfiguration(LogLevel::WARNING),
            new StaticApplicationContextProcessor([
                'application' => 'QMDB',
                'environment' => 'test',
                'phase' => 'P1',
                'batch' => 'QMDB-P1-B07',
                'baseline' => 'QMDB-P0-FRZ-001',
            ]),
            $stream,
        ))->create();
        $logger->info('http.request.started');
        rewind($stream);

        self::assertSame('', stream_get_contents($stream));
        fclose($stream);
    }

    public function testLoggingFailureEmitsOneMinimalFallbackAndDoesNotThrow(): void
    {
        $fallback = [];
        $logger = new ResilientLogger(
            new FailingPsrLogger(),
            new SensitiveValueRedactor(new SensitiveKeyMatcher()),
            new LogContextSanitizer(),
            new InternalErrorChannel(static function (string $message) use (&$fallback): void {
                $fallback[] = $message;
            }),
        );

        $logger->error('http.request.completed', ['password' => 'QMDB_MUST_NOT_APPEAR']);

        self::assertCount(1, $fallback);
        self::assertSame(
            'qmdb logging failure [event=http.request.completed] [type=RuntimeException]',
            $fallback[0],
        );
        self::assertStringNotContainsString('QMDB_MUST_NOT_APPEAR', $fallback[0]);
    }

    public function testFailingFallbackDoesNotCreateRecursiveFailure(): void
    {
        $attempts = 0;
        $logger = new ResilientLogger(
            new FailingPsrLogger(),
            new SensitiveValueRedactor(new SensitiveKeyMatcher()),
            new LogContextSanitizer(),
            new InternalErrorChannel(static function (string $message) use (&$attempts): void {
                ++$attempts;
                throw new \RuntimeException('fallback failure');
            }),
        );

        $logger->error('application.exception');

        self::assertSame(1, $attempts);
    }
}
