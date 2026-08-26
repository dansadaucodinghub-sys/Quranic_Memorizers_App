<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(private CorrelationIdGenerator $correlationIdGenerator)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $correlationId = $this->correlationIdGenerator->generate();
        $correlatedRequest = $request->withAttribute(RequestContextAttributes::REQUEST_ID, $correlationId);

        return $handler->handle($correlatedRequest)
            ->withHeader('X-Request-ID', $correlationId->value());
    }
}
