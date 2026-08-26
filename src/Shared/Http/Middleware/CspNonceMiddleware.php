<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Presentation\Security\CspNonceGenerator;

final readonly class CspNonceMiddleware implements MiddlewareInterface
{
    public function __construct(private CspNonceGenerator $generator)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute(
            RequestContextAttributes::CSP_NONCE,
            $this->generator->generate(),
        ));
    }
}
