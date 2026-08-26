<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationAttributes;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\SessionAuthenticationService;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;

final readonly class SessionAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionAuthenticationService $authentication,
        private SessionCookieFactory $cookies,
        private AuthenticationCookieResponseDecorator $decorator,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === '/health/live' || $path === '/health/ready') {
            return $handler->handle($request);
        }
        $raw = $request->getCookieParams()[$this->cookies->name()] ?? null;
        $result = $this->authentication->authenticate(is_string($raw) ? $raw : null);
        if ($result->context !== null) {
            $request = $request->withAttribute(AuthenticationAttributes::ACCOUNT, $result->context);
        }
        $response = $handler->handle($request);
        if ($result->cookieInstruction !== null) {
            $response = $this->decorator->apply($response, [$result->cookieInstruction]);
        }

        return $response;
    }
}
