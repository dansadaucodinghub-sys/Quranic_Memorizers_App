<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Http\Middleware\CorrelationIdMiddleware;
use Qmdb\Shared\Http\Middleware\HttpRequestLoggingMiddleware;
use Qmdb\Shared\Http\Middleware\SecurityHeadersMiddleware;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Tests\Support\Http\CallableRequestHandler;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Observability\FakeMonotonicClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;
use RuntimeException;

final class HttpObservabilityMiddlewareTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function responseStatuses(): iterable
    {
        foreach ([200, 400, 404, 405, 500, 503] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[DataProvider('responseStatuses')]
    public function testCorrelationIsServerOwnedAndAppliedToEveryStatus(int $status): void
    {
        $serverId = str_repeat('a', 32);
        $attribute = null;
        $middleware = new CorrelationIdMiddleware(new SequenceCorrelationIdGenerator([$serverId]));
        $request = HttpTestFactory::request()->withHeader('X-Request-ID', str_repeat('f', 32));
        $response = $middleware->process($request, new CallableRequestHandler(
            static function (ServerRequestInterface $request) use (&$attribute, $status): Response {
                $attribute = $request->getAttribute(RequestContextAttributes::REQUEST_ID);

                return (new Response($status))->withHeader('X-Request-ID', str_repeat('e', 32));
            },
        ));

        self::assertInstanceOf(CorrelationId::class, $attribute);
        self::assertSame($serverId, $attribute->value());
        self::assertSame($serverId, $response->getHeaderLine('X-Request-ID'));
        self::assertNotSame(str_repeat('f', 32), $response->getHeaderLine('X-Request-ID'));
    }

    public function testRepeatedRequestsDoNotShareCorrelationState(): void
    {
        $middleware = new CorrelationIdMiddleware(new SequenceCorrelationIdGenerator([
            str_repeat('a', 32),
            str_repeat('b', 32),
        ]));
        $handler = new CallableRequestHandler(
            static fn (ServerRequestInterface $request): Response => new Response(),
        );

        $first = $middleware->process(HttpTestFactory::request(), $handler);
        $second = $middleware->process(HttpTestFactory::request(), $handler);

        self::assertNotSame(
            $first->getHeaderLine('X-Request-ID'),
            $second->getHeaderLine('X-Request-ID'),
        );
    }

    public function testSecurityHeadersOverrideWeakerValuesAndPreserveUnrelatedHeaders(): void
    {
        $middleware = new SecurityHeadersMiddleware(ApplicationEnvironment::LOCAL);
        $response = $middleware->process(
            HttpTestFactory::request(),
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => (new Response())
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Content-Security-Policy', "default-src *")
                ->withHeader('X-Powered-By', 'PHP')
                ->withHeader('X-Custom-Safe', 'retained')),
        );

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('none', $response->getHeaderLine('X-Permitted-Cross-Domain-Policies'));
        self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Resource-Policy'));
        self::assertSame(
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            $response->getHeaderLine('Permissions-Policy'),
        );
        self::assertSame(
            "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
        self::assertSame('retained', $response->getHeaderLine('X-Custom-Safe'));
        self::assertFalse($response->hasHeader('X-Powered-By'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testHstsRequiresProductionLikeEnvironmentAndDirectHttps(): void
    {
        $handler = new CallableRequestHandler(
            static fn (ServerRequestInterface $request): Response => new Response(),
        );
        $https = new ServerRequest('GET', 'https://example.test/');
        $httpWithForwarded = (new ServerRequest('GET', 'http://example.test/'))
            ->withHeader('X-Forwarded-Proto', 'https');

        foreach ([ApplicationEnvironment::STAGING, ApplicationEnvironment::PRODUCTION] as $environment) {
            $response = (new SecurityHeadersMiddleware($environment))->process($https, $handler);
            self::assertSame(
                'max-age=31536000; includeSubDomains',
                $response->getHeaderLine('Strict-Transport-Security'),
            );
        }

        self::assertFalse((new SecurityHeadersMiddleware(ApplicationEnvironment::LOCAL))
            ->process($https, $handler)->hasHeader('Strict-Transport-Security'));
        self::assertFalse((new SecurityHeadersMiddleware(ApplicationEnvironment::PRODUCTION))
            ->process($httpWithForwarded, $handler)->hasHeader('Strict-Transport-Security'));
    }

    public function testRequestLoggingUsesOnlyBoundedSafeFieldsAndFinalStatus(): void
    {
        $events = new InMemoryEventLogger();
        $requestId = str_repeat('c', 32);
        $request = HttpTestFactory::request('POST', '/private?token=QMDB_QUERY_SECRET')
            ->withHeader('Authorization', 'Bearer QMDB_AUTH_SECRET')
            ->withHeader('Cookie', 'session=QMDB_COOKIE_SECRET')
            ->withParsedBody(['password' => 'QMDB_BODY_SECRET'])
            ->withAttribute(RequestContextAttributes::REQUEST_ID, new CorrelationId($requestId));
        $middleware = new HttpRequestLoggingMiddleware(
            $events,
            new FakeMonotonicClock([1_000_000_000, 1_003_420_000]),
        );

        $response = $middleware->process(
            $request,
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => new Response(500)),
        );
        $encoded = json_encode($events->records(), JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['http.request.started', 'http.request.completed'], array_column(
            $events->records(),
            'event',
        ));
        self::assertSame(LogLevel::ERROR, $events->records()[1]['level']);
        self::assertSame($requestId, $events->records()[1]['context']['request_id']);
        self::assertSame('POST', $events->records()[1]['context']['method']);
        self::assertSame(500, $events->records()[1]['context']['status']);
        self::assertSame(3.42, $events->records()[1]['context']['duration_ms']);
        $unsafeValues = [
            'QMDB_QUERY_SECRET',
            'QMDB_AUTH_SECRET',
            'QMDB_COOKIE_SECRET',
            'QMDB_BODY_SECRET',
            '/private',
        ];
        foreach ($unsafeValues as $unsafe) {
            self::assertStringNotContainsString($unsafe, $encoded);
        }
    }

    public function testLoggingFailureDoesNotChangePrimaryResponse(): void
    {
        $logger = new class implements EventLogger {
            public function log(LogLevel $level, LogEventName $event, array $context = []): void
            {
                throw new RuntimeException('logging failed');
            }
        };
        $middleware = new HttpRequestLoggingMiddleware($logger, new FakeMonotonicClock([1, 2]));
        $request = HttpTestFactory::request()->withAttribute(
            RequestContextAttributes::REQUEST_ID,
            new CorrelationId(str_repeat('d', 32)),
        );

        $response = $middleware->process(
            $request,
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => new Response(204)),
        );

        self::assertSame(204, $response->getStatusCode());
    }
}
