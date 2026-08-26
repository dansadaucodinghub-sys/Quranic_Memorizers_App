<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Routing\MatchedRoute;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class ControllerDispatcher
{
    public function dispatch(MatchedRoute $matchedRoute, ServerRequestInterface $request): ResponseInterface
    {
        $request = $request
            ->withAttribute(RouteAttributes::NAME, $matchedRoute->route()->name())
            ->withAttribute(RouteAttributes::PARAMETERS, $matchedRoute->parameters())
            ->withAttribute(RouteAttributes::METHOD, $matchedRoute->effectiveMethod()->value);

        return $matchedRoute->route()->controller()->handle($request);
    }
}
