<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Observability\Error;

use ErrorException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Observability\Error\BootstrapFailureReporter;
use Qmdb\Shared\Observability\Error\BootstrapFailureResponder;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Error\FatalErrorShutdownReporter;
use Qmdb\Shared\Observability\Error\InternalErrorChannel;
use Qmdb\Shared\Observability\Error\PhpErrorHandler;
use Qmdb\Shared\Observability\Error\StructuredThrowableReporter;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\SensitiveKeyMatcher;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\FixedLastErrorProvider;
use Qmdb\Tests\Support\Observability\SafeTestException;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;

final class ErrorHandlingTest extends TestCase
{
    public function testThrowableReporterExcludesMessageTraceAndAbsolutePath(): void
    {
        $events = new InMemoryEventLogger();
        $reporter = new StructuredThrowableReporter(
            $events,
            new ExceptionFingerprint(),
            new SensitiveValueRedactor(new SensitiveKeyMatcher()),
            new LogContextSanitizer(),
        );
        $exception = new SafeTestException('QMDB_EXCEPTION_SECRET_4721');
        $requestId = str_repeat('a', 32);

        $reporter->report(
            $exception,
            (new SequenceCorrelationIdGenerator([$requestId]))->generate(),
            ['password' => 'QMDB_PASSWORD_SECRET'],
        );
        $encoded = json_encode($events->records(), JSON_THROW_ON_ERROR);
        $context = $events->records()[0]['context'];

        self::assertSame('application.exception', $events->records()[0]['event']);
        self::assertSame($requestId, $context['request_id']);
        self::assertSame(SafeTestException::class, $context['exception_class']);
        self::assertIsString($context['exception_fingerprint']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $context['exception_fingerprint']);
        self::assertSame('SAFE_TEST_FAILURE', $context['safe_error_code']);
        self::assertSame('[REDACTED]', $context['password']);
        self::assertSame('[REDACTED]', $context['authorization']);
        self::assertStringNotContainsString('QMDB_EXCEPTION_SECRET_4721', $encoded);
        self::assertStringNotContainsString(__FILE__, $encoded);
        self::assertStringNotContainsString('trace', strtolower($encoded));
    }

    public function testFingerprintIsStableAndLocationSensitive(): void
    {
        $fingerprint = new ExceptionFingerprint();

        self::assertSame(
            $fingerprint->forLocation('RuntimeException', '/one/example.php', 10),
            $fingerprint->forLocation('RuntimeException', '/two/example.php', 10),
        );
        self::assertNotSame(
            $fingerprint->forLocation('RuntimeException', 'example.php', 10),
            $fingerprint->forLocation('RuntimeException', 'example.php', 11),
        );
    }

    public function testPhpWarningsAndNoticesConvertWithSeverity(): void
    {
        $handler = new PhpErrorHandler();
        $previous = error_reporting(E_ALL);

        try {
            foreach ([E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_RECOVERABLE_ERROR] as $severity) {
                try {
                    $handler->handle($severity, 'controlled test error', __FILE__, __LINE__);
                    self::fail('Expected ErrorException was not thrown.');
                } catch (ErrorException $exception) {
                    self::assertSame($severity, $exception->getSeverity());
                }
            }
        } finally {
            error_reporting($previous);
        }
    }

    public function testPhpErrorRegistrationIsIdempotentAndRestored(): void
    {
        $handler = new PhpErrorHandler();
        $handler->register();
        $handler->register();
        self::assertTrue($handler->isRegistered());

        $handler->unregister();
        $handler->unregister();
        self::assertFalse($handler->isRegistered());
    }

    public function testErrorsOutsideReportingMaskAreIgnored(): void
    {
        $previous = error_reporting(0);

        try {
            self::assertFalse((new PhpErrorHandler())->handle(E_WARNING, 'ignored', __FILE__, __LINE__));
        } finally {
            error_reporting($previous);
        }
    }

    public function testFatalReporterLogsOnlySafeFatalClassification(): void
    {
        $events = new InMemoryEventLogger();
        $secret = 'QMDB_FATAL_SECRET_9172';
        $reporter = new FatalErrorShutdownReporter(
            new FixedLastErrorProvider([
                'type' => E_ERROR,
                'message' => $secret,
                'file' => 'C:\\private\\application.php',
                'line' => 91,
            ]),
            $events,
            new SequenceCorrelationIdGenerator([str_repeat('b', 32)]),
            new ExceptionFingerprint(),
        );

        $reporter->reportLastError();
        $encoded = json_encode($events->records(), JSON_THROW_ON_ERROR);

        self::assertSame('application.fatal', $events->records()[0]['event']);
        self::assertSame(E_ERROR, $events->records()[0]['context']['fatal_type']);
        self::assertStringNotContainsString($secret, $encoded);
        self::assertStringNotContainsString('private', $encoded);
    }

    public function testNonFatalLastErrorIsIgnored(): void
    {
        $events = new InMemoryEventLogger();
        $reporter = new FatalErrorShutdownReporter(
            new FixedLastErrorProvider([
                'type' => E_WARNING,
                'message' => 'ignored',
                'file' => 'example.php',
                'line' => 1,
            ]),
            $events,
            new SequenceCorrelationIdGenerator([str_repeat('c', 32)]),
            new ExceptionFingerprint(),
        );

        $reporter->reportLastError();

        self::assertSame([], $events->records());
    }

    public function testBootstrapFailureHasMatchingSafePublicReference(): void
    {
        $messages = [];
        $requestId = str_repeat('d', 32);
        $responder = new BootstrapFailureResponder(
            new SequenceCorrelationIdGenerator([$requestId]),
            new BootstrapFailureReporter(new InternalErrorChannel(
                static function (string $message) use (&$messages): void {
                    $messages[] = $message;
                },
            )),
        );

        $response = $responder->create('unexpected_throwable');
        $body = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertSame(500, $response->status());
        self::assertSame($requestId, $response->headers()['X-Request-ID']);
        self::assertSame($requestId, $body['request_id']);
        self::assertStringNotContainsString(__FILE__, $response->body());
        self::assertSame(
            'qmdb bootstrap failure [request_id=' . $requestId . '] [type=unexpected_throwable]',
            $messages[0],
        );
    }
}
