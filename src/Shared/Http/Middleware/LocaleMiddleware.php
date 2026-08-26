<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Localization\LocaleContext;
use Qmdb\Shared\Localization\LocaleResolver;

final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(private LocaleResolver $resolver)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = new LocaleContext($this->resolver->resolve($request));
        $response = $handler->handle($request->withAttribute(RequestContextAttributes::LOCALE, $context));

        return $response
            ->withHeader('Content-Language', $context->locale()->value())
            ->withAddedHeader('Vary', 'Accept-Language, X-QMDB-Locale');
    }
}
