<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware;

    /** @param iterable<mixed> $middleware */
    public function __construct(iterable $middleware, private RequestHandlerInterface $finalHandler)
    {
        $validated = [];
        foreach ($middleware as $entry) {
            if (!$entry instanceof MiddlewareInterface) {
                throw new InvalidArgumentException('Every middleware entry must implement PSR-15 MiddlewareInterface.');
            }

            $validated[] = $entry;
        }

        $this->middleware = $validated;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new MiddlewareRequestHandler($this->middleware, $this->finalHandler, 0))->handle($request);
    }
}
