<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Error\ThrowableReporter;
use Throwable;

final readonly class ExceptionHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $responseFactory,
        private ThrowableReporter $throwableReporter,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            $correlationId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
            if ($correlationId instanceof CorrelationId) {
                $this->throwableReporter->report($throwable, $correlationId);
            }

            return $this->responseFactory->createForRequest(
                ProblemDetails::internalServerError(),
                $request,
            );
        }
    }
}
