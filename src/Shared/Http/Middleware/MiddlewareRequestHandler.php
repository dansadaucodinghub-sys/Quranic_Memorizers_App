<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @internal Request-local PSR-15 dispatch state. */
final readonly class MiddlewareRequestHandler implements RequestHandlerInterface
{
    /** @param list<MiddlewareInterface> $middleware */
    public function __construct(
        private array $middleware,
        private RequestHandlerInterface $finalHandler,
        private int $index,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->middleware[$this->index] ?? null;
        if ($middleware === null) {
            return $this->finalHandler->handle($request);
        }

        return $middleware->process(
            $request,
            new self($this->middleware, $this->finalHandler, $this->index + 1),
        );
    }
}
