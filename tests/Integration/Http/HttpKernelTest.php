<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Middleware\RequestTargetValidationMiddleware;
use Qmdb\Shared\Http\Request\RequestTargetValidator;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Shared\Http\Routing\Router;
use Qmdb\Shared\Http\Routing\RoutingRequestHandler;
use Qmdb\Tests\Support\Http\CallableController;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Observability\RecordingThrowableReporter;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;
use RuntimeException;

final class HttpKernelTest extends TestCase
{
    public function testUnknownRouteReturnsGeneric404(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('GET', '/not-registered'),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('ROUTE_NOT_FOUND', $this->body($response)['code']);
        self::assertStringNotContainsString('health/live', (string) $response->getBody());
    }

    public function testMethodNotAllowedReturnsOnlyDeterministicAllowMetadata(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('POST', '/health/live'),
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
        self::assertSame('METHOD_NOT_ALLOWED', $this->body($response)['code']);
    }

    public function testAutomaticOptionsReturnsEmpty204WithoutControllerMetadata(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('OPTIONS', '/health/live'),
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
        self::assertSame('', (string) $response->getBody());
    }

    public function testHeadExecutesGetAndRetainsResponseForEmitterSuppression(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('HEAD', '/health/live'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"alive"}', (string) $response->getBody());
    }

    public function testMalformedAndEncodedSlashTargetsReturn400(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();

        foreach (['/%ZZ', '/%2F'] as $target) {
            $response = $runtime->handle(HttpTestFactory::request('GET', $target));
            self::assertSame(400, $response->getStatusCode());
            self::assertSame('REQUEST_TARGET_INVALID', $this->body($response)['code']);
            self::assertStringNotContainsString($target, (string) $response->getBody());
        }
    }

    public function testOneKernelHandlesMultipleSequentialRequests(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();

        self::assertSame(200, $runtime->handle(HttpTestFactory::request('GET', '/'))->getStatusCode());
        self::assertSame(404, $runtime->handle(HttpTestFactory::request('GET', '/missing'))->getStatusCode());
        self::assertSame(503, $runtime->handle(HttpTestFactory::request('GET', '/health/ready'))->getStatusCode());
    }

    public function testThrowingControllerIsContainedByOutermostExceptionBoundary(): void
    {
        $secret = 'QMDB_HTTP_SECRET_73e1';
        $kernel = $this->kernel(new CallableController(
            static function (ServerRequestInterface $request) use ($secret): never {
                throw new RuntimeException($secret . ' ' . __FILE__);
            },
        ));

        $response = $kernel->handle(HttpTestFactory::request('GET', '/throw'));
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString($secret, $body);
        self::assertStringNotContainsString(__FILE__, $body);
        self::assertStringNotContainsString('Stack trace', $body);
    }

    private function kernel(CallableController $controller): HttpKernel
    {
        $psr17 = HttpTestFactory::psr17();
        $problems = HttpTestFactory::problems();
        $routing = new RoutingRequestHandler(
            new Router(new RouteCollection(
                new Route('test.throw', [HttpMethod::GET], new RoutePattern('/throw'), $controller),
            )),
            new ControllerDispatcher(),
            $problems,
            $psr17,
        );

        return new HttpKernel([
            new ExceptionHandlingMiddleware($problems, new RecordingThrowableReporter()),
            new RequestTargetValidationMiddleware(new RequestTargetValidator(), $problems),
        ], $routing);
    }

    /** @return array<mixed> */
    private function body(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
