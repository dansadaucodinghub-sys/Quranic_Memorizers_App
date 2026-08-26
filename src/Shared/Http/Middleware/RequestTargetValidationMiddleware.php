<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Request\RequestTargetValidator;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class RequestTargetValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestTargetValidator $validator,
        private ProblemDetailsResponseFactory $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->validator->validate($request->getRequestTarget());
        if (!$result->isValid()) {
            return $this->responseFactory->createForRequest(ProblemDetails::badRequest(), $request);
        }

        return $handler->handle(
            $request->withAttribute(RouteAttributes::VALIDATED_PATH, $result->decodedPath()),
        );
    }
}
