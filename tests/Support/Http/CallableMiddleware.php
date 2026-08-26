<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CallableMiddleware implements MiddlewareInterface
{
    /** @param Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $processor */
    public function __construct(private Closure $processor)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->processor)($request, $handler);
    }
}
