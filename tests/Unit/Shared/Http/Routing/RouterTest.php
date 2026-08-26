<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Routing;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Routing\AutomaticOptions;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\MatchedRoute;
use Qmdb\Shared\Http\Routing\MethodNotAllowed;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RouteNotFound;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Shared\Http\Routing\Router;
use Qmdb\Tests\Support\Http\CallableController;

final class RouterTest extends TestCase
{
    public function testStaticRouteWinsOverParameterizedRouteRegardlessOfRegistrationOrder(): void
    {
        $router = new Router(new RouteCollection(
            $this->route('records.show', [HttpMethod::GET], '/records/{recordId}'),
            $this->route('records.active', [HttpMethod::GET], '/records/active'),
        ));

        $result = $router->match(HttpMethod::GET, '/records/active');

        self::assertInstanceOf(MatchedRoute::class, $result);
        self::assertSame('records.active', $result->route()->name());
        self::assertSame([], $result->parameters());
    }

    public function testParameterizedRouteExtractsUnicodeWithoutDoubleDecoding(): void
    {
        $router = new Router(new RouteCollection(
            $this->route('records.show', [HttpMethod::GET], '/records/{recordId}'),
        ));

        $unicode = $router->match(HttpMethod::GET, '/records/حفص');
        $percentLiteral = $router->match(HttpMethod::GET, '/records/%2F');

        self::assertInstanceOf(MatchedRoute::class, $unicode);
        self::assertSame(['recordId' => 'حفص'], $unicode->parameters());
        self::assertInstanceOf(MatchedRoute::class, $percentLiteral);
        self::assertSame(['recordId' => '%2F'], $percentLiteral->parameters());
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        self::assertInstanceOf(
            RouteNotFound::class,
            $this->router()->match(HttpMethod::GET, '/missing'),
        );
    }

    public function testUnsupportedMethodReturnsDeterministicAllowMethods(): void
    {
        $result = $this->router()->match(null, '/records');

        self::assertInstanceOf(MethodNotAllowed::class, $result);
        self::assertSame('GET, HEAD, OPTIONS', $result->allowHeader());
    }

    public function testAutomaticHeadFallsBackToGet(): void
    {
        $result = $this->router()->match(HttpMethod::HEAD, '/records');

        self::assertInstanceOf(MatchedRoute::class, $result);
        self::assertTrue($result->isHeadFallback());
        self::assertSame(HttpMethod::GET, $result->effectiveMethod());
    }

    public function testExplicitHeadTakesPrecedence(): void
    {
        $router = new Router(new RouteCollection(
            $this->route('records.index', [HttpMethod::GET], '/records'),
            $this->route('records.head', [HttpMethod::HEAD], '/records'),
        ));
        $result = $router->match(HttpMethod::HEAD, '/records');

        self::assertInstanceOf(MatchedRoute::class, $result);
        self::assertSame('records.head', $result->route()->name());
        self::assertFalse($result->isHeadFallback());
    }

    public function testKnownPathReceivesAutomaticOptions(): void
    {
        $result = $this->router()->match(HttpMethod::OPTIONS, '/records');

        self::assertInstanceOf(AutomaticOptions::class, $result);
        self::assertSame('GET, HEAD, OPTIONS', $result->allowHeader());
    }

    public function testUnknownPathOptionsReturnsNotFound(): void
    {
        self::assertInstanceOf(
            RouteNotFound::class,
            $this->router()->match(HttpMethod::OPTIONS, '/missing'),
        );
    }

    public function testSameRouterHandlesRepeatedRequestsWithoutStateLeakage(): void
    {
        $router = $this->router();

        self::assertInstanceOf(MatchedRoute::class, $router->match(HttpMethod::GET, '/records'));
        self::assertInstanceOf(RouteNotFound::class, $router->match(HttpMethod::GET, '/missing'));
        self::assertInstanceOf(MatchedRoute::class, $router->match(HttpMethod::GET, '/records'));
    }

    private function router(): Router
    {
        return new Router(new RouteCollection(
            $this->route('records.index', [HttpMethod::GET], '/records'),
        ));
    }

    /** @param list<HttpMethod> $methods */
    private function route(string $name, array $methods, string $pattern): Route
    {
        return new Route(
            $name,
            $methods,
            new RoutePattern($pattern),
            new CallableController(static fn (): Response => new Response()),
        );
    }
}
