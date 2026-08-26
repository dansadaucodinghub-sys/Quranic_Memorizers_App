<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Controller;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\MatchedRoute;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Http\Routing\RouteParameter;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Tests\Support\Http\CallableController;
use Qmdb\Tests\Support\Http\HttpTestFactory;

final class ControllerDispatcherTest extends TestCase
{
    public function testDispatcherAttachesRouteAttributesAndPreservesExistingAttributes(): void
    {
        $calls = 0;
        $received = null;
        $expectedResponse = new Response(202);
        $controller = new CallableController(
            static function (ServerRequestInterface $request) use (&$calls, &$received, $expectedResponse): Response {
                ++$calls;
                $received = $request;

                return $expectedResponse;
            },
        );
        $route = new Route(
            'records.show',
            [HttpMethod::GET],
            new RoutePattern('/records/{recordId}'),
            $controller,
        );
        $matched = new MatchedRoute(
            $route,
            [new RouteParameter('recordId', 'abc123')],
            HttpMethod::GET,
            false,
        );
        $request = HttpTestFactory::request('GET', '/uri-that-must-not-be-reparsed')
            ->withAttribute('existing', 'preserved');

        $response = (new ControllerDispatcher())->dispatch($matched, $request);

        self::assertSame($expectedResponse, $response);
        self::assertSame(1, $calls);
        self::assertInstanceOf(ServerRequestInterface::class, $received);
        self::assertSame('preserved', $received->getAttribute('existing'));
        self::assertSame('records.show', $received->getAttribute(RouteAttributes::NAME));
        self::assertSame(['recordId' => 'abc123'], $received->getAttribute(RouteAttributes::PARAMETERS));
        self::assertSame('GET', $received->getAttribute(RouteAttributes::METHOD));
    }
}
