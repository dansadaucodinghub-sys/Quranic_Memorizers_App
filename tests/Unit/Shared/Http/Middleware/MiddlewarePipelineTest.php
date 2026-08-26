<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Middleware;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Middleware\MiddlewarePipeline;
use Qmdb\Tests\Support\Http\CallableMiddleware;
use Qmdb\Tests\Support\Http\CallableRequestHandler;
use Qmdb\Tests\Support\Http\HttpTestFactory;

final class MiddlewarePipelineTest extends TestCase
{
    public function testDeclaredBeforeAndAfterOrderIsPreserved(): void
    {
        $events = [];
        $first = new CallableMiddleware(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$events): ResponseInterface {
                $events[] = 'first.before';
                $response = $handler->handle($request);
                $events[] = 'first.after';

                return $response;
            },
        );
        $second = new CallableMiddleware(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$events): ResponseInterface {
                $events[] = 'second.before';
                $response = $handler->handle($request);
                $events[] = 'second.after';

                return $response;
            },
        );
        $final = new CallableRequestHandler(
            static function (ServerRequestInterface $request) use (&$events): Response {
                $events[] = 'handler';

                return new Response(200);
            },
        );

        (new MiddlewarePipeline([$first, $second], $final))->handle(HttpTestFactory::request());

        self::assertSame(
            ['first.before', 'second.before', 'handler', 'second.after', 'first.after'],
            $events,
        );
    }

    public function testShortCircuitDoesNotInvokeLaterMiddlewareOrFinalHandler(): void
    {
        $calls = 0;
        $shortCircuit = new CallableMiddleware(
            static fn (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): Response => new Response(202),
        );
        $final = new CallableRequestHandler(
            static function (ServerRequestInterface $request) use (&$calls): Response {
                ++$calls;

                return new Response();
            },
        );

        $response = (new MiddlewarePipeline([$shortCircuit], $final))->handle(HttpTestFactory::request());

        self::assertSame(202, $response->getStatusCode());
        self::assertSame(0, $calls);
    }

    public function testRepeatedRequestsUseFreshDispatchState(): void
    {
        $calls = 0;
        $middleware = new CallableMiddleware(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$calls): ResponseInterface {
                ++$calls;

                return $handler->handle($request);
            },
        );
        $pipeline = new MiddlewarePipeline(
            [$middleware],
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => new Response()),
        );

        $pipeline->handle(HttpTestFactory::request());
        $pipeline->handle(HttpTestFactory::request());

        self::assertSame(2, $calls);
    }

    public function testCallerMutationOfInputArrayCannotChangePipeline(): void
    {
        $middleware = [];
        $pipeline = new MiddlewarePipeline(
            $middleware,
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => new Response(200)),
        );
        $middleware[] = new CallableMiddleware(
            static fn (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): Response => new Response(500),
        );

        self::assertSame(200, $pipeline->handle(HttpTestFactory::request())->getStatusCode());
    }

    public function testInvalidMiddlewareEntryFailsDuringConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MiddlewarePipeline(
            ['not-middleware'],
            new CallableRequestHandler(static fn (ServerRequestInterface $request): Response => new Response()),
        );
    }
}
