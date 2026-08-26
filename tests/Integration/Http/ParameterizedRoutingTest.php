<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Middleware\RequestTargetValidationMiddleware;
use Qmdb\Shared\Http\Request\RequestTargetValidator;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Shared\Http\Routing\Router;
use Qmdb\Shared\Http\Routing\RoutingRequestHandler;
use Qmdb\Tests\Support\Http\CallableController;
use Qmdb\Tests\Support\Http\HttpTestFactory;

final class ParameterizedRoutingTest extends TestCase
{
    public function testParametersSupportLiteralAndEncodedUnicodeWithoutDoubleDecoding(): void
    {
        $kernel = $this->kernel();

        foreach (
            [
            '/records/حفص' => 'حفص',
            '/records/%D8%AD%D9%81%D8%B5' => 'حفص',
            '/records/%252F' => '%2F',
            ] as $target => $expected
        ) {
            $response = $kernel->handle(HttpTestFactory::request('GET', $target));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame(
                ['recordId' => $expected],
                json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testStaticRoutePrecedenceAndEncodedSlashRejection(): void
    {
        $kernel = $this->kernel();

        $static = $kernel->handle(HttpTestFactory::request('GET', '/records/current'));
        $encodedSlash = $kernel->handle(HttpTestFactory::request('GET', '/records/%2F'));

        self::assertSame('{"static":true}', (string) $static->getBody());
        self::assertSame(400, $encodedSlash->getStatusCode());
    }

    private function kernel(): HttpKernel
    {
        $psr17 = HttpTestFactory::psr17();
        $json = HttpTestFactory::json();
        $problems = HttpTestFactory::problems();
        $parameterController = new CallableController(
            static function (ServerRequestInterface $request) use ($json): ResponseInterface {
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);

                return $json->create(is_array($parameters) ? $parameters : []);
            },
        );
        $staticController = new CallableController(
            static fn (ServerRequestInterface $request): ResponseInterface => $json->create(['static' => true]),
        );
        $routes = new RouteCollection(
            new Route(
                'records.show',
                [HttpMethod::GET],
                new RoutePattern('/records/{recordId}'),
                $parameterController,
            ),
            new Route(
                'records.current',
                [HttpMethod::GET],
                new RoutePattern('/records/current'),
                $staticController,
            ),
        );
        $routing = new RoutingRequestHandler(
            new Router($routes),
            new ControllerDispatcher(),
            $problems,
            $psr17,
        );

        return new HttpKernel([
            new RequestTargetValidationMiddleware(new RequestTargetValidator(), $problems),
        ], $routing);
    }
}
