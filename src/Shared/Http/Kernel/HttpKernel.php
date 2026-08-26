<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Middleware\MiddlewarePipeline;

final readonly class HttpKernel implements RequestHandlerInterface
{
    private MiddlewarePipeline $pipeline;

    /** @param list<MiddlewareInterface> $middleware */
    public function __construct(array $middleware, RequestHandlerInterface $routingHandler)
    {
        $this->pipeline = new MiddlewarePipeline($middleware, $routingHandler);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }
}
