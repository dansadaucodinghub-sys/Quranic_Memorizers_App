<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http\Observability;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Middleware\CorrelationIdMiddleware;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Middleware\HttpRequestLoggingMiddleware;
use Qmdb\Shared\Http\Middleware\SecurityHeadersMiddleware;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Error\StructuredThrowableReporter;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\SensitiveKeyMatcher;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Qmdb\Tests\Support\Http\CallableRequestHandler;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;
use Qmdb\Tests\Support\Observability\FakeMonotonicClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;
use RuntimeException;

final class HttpObservabilityIntegrationTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function productionResponses(): iterable
    {
        yield 'root' => ['GET', '/', 200];
        yield 'liveness' => ['GET', '/health/live', 200];
        yield 'readiness unavailable' => ['GET', '/health/ready', 503];
        yield 'api about' => ['GET', '/api/v1/system/about', 200];
        yield 'head' => ['HEAD', '/', 200];
        yield 'options' => ['OPTIONS', '/', 204];
        yield 'bad target' => ['GET', '/%2F', 400];
        yield 'not found' => ['GET', '/missing', 404];
        yield 'method not allowed' => ['POST', '/health/live', 405];
    }

    #[DataProvider('productionResponses')]
    public function testProductionPipelineCorrelatesAndHardensEveryResponse(
        string $method,
        string $target,
        int $status,
    ): void {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request($method, $target)->withHeader('X-Request-ID', str_repeat('f', 32)),
        );
        $requestId = $response->getHeaderLine('X-Request-ID');

        self::assertSame($status, $response->getStatusCode());
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $requestId);
        self::assertNotSame(str_repeat('f', 32), $requestId);
        $this->assertSecurityHeaders($response->getHeaders());
        self::assertFalse($response->hasHeader('X-Powered-By'));

        if (in_array($status, [400, 404, 405], true)) {
            $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            self::assertSame($requestId, $body['request_id']);
            self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        }
    }

    public function testSeparateProductionRequestsReceiveSeparateIdentifiers(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $first = $runtime->handle(HttpTestFactory::request());
        $second = $runtime->handle(HttpTestFactory::request());

        self::assertNotSame(
            $first->getHeaderLine('X-Request-ID'),
            $second->getHeaderLine('X-Request-ID'),
        );
    }

    public function testUnexpectedFailureIsCorrelatedReportedHardenedAndCompletionLogged(): void
    {
        $requestId = str_repeat('e', 32);
        $events = new InMemoryEventLogger();
        $redactor = new SensitiveValueRedactor(new SensitiveKeyMatcher());
        $sanitizer = new LogContextSanitizer();
        $reporter = new StructuredThrowableReporter(
            $events,
            new ExceptionFingerprint(),
            $redactor,
            $sanitizer,
        );
        $kernel = new HttpKernel([
            new CorrelationIdMiddleware(new SequenceCorrelationIdGenerator([$requestId])),
            new SecurityHeadersMiddleware(ApplicationEnvironment::TEST),
            new HttpRequestLoggingMiddleware($events, new FakeMonotonicClock([100, 3_420_100])),
            new ExceptionHandlingMiddleware(HttpTestFactory::problems(), $reporter),
        ], new CallableRequestHandler(
            static function (ServerRequestInterface $request): never {
                throw new RuntimeException('QMDB_HTTP_EXCEPTION_SECRET at ' . __FILE__);
            },
        ));

        $response = $kernel->handle(HttpTestFactory::request());
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $encodedEvents = json_encode($events->records(), JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($requestId, $response->getHeaderLine('X-Request-ID'));
        self::assertSame($requestId, $decoded['request_id']);
        self::assertSame(
            ['http.request.started', 'application.exception', 'http.request.completed'],
            array_column($events->records(), 'event'),
        );
        self::assertStringNotContainsString('QMDB_HTTP_EXCEPTION_SECRET', $body . $encodedEvents);
        self::assertStringNotContainsString(__FILE__, $body . $encodedEvents);
        $this->assertSecurityHeaders($response->getHeaders());
    }

    /** @param array<array<string>> $headers */
    private function assertSecurityHeaders(array $headers): void
    {
        foreach (
            [
            'X-Content-Type-Options',
            'Referrer-Policy',
            'X-Frame-Options',
            'X-Permitted-Cross-Domain-Policies',
            'Cross-Origin-Resource-Policy',
            'Permissions-Policy',
            'Content-Security-Policy',
            ] as $name
        ) {
            self::assertArrayHasKey($name, $headers);
        }
    }
}
