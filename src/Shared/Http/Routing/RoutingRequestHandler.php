<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;

final readonly class RoutingRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private ControllerDispatcher $dispatcher,
        private ProblemDetailsResponseFactory $problemResponseFactory,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getAttribute(RouteAttributes::VALIDATED_PATH);
        if (!is_string($path)) {
            return $this->problemResponseFactory->createForRequest(ProblemDetails::badRequest(), $request);
        }

        $result = $this->router->match(HttpMethod::tryParse($request->getMethod()), $path);

        if ($result instanceof MatchedRoute) {
            return $this->dispatcher->dispatch($result, $request);
        }

        if ($result instanceof AutomaticOptions) {
            return $this->responseFactory
                ->createResponse(204)
                ->withHeader('Allow', $result->allowHeader())
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        }

        if ($result instanceof MethodNotAllowed) {
            return $this->problemResponseFactory->createForRequest(
                ProblemDetails::methodNotAllowed(),
                $request,
                ['Allow' => $result->allowHeader()],
            );
        }

        return $this->problemResponseFactory->createForRequest(ProblemDetails::notFound(), $request);
    }
}
