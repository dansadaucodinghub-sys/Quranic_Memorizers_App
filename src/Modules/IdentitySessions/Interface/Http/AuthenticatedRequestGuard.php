<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationAttributes;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class AuthenticatedRequestGuard
{
    public function __construct(
        private FragmentRequestDetector $fragments,
        private JsonResponseFactory $json,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function context(ServerRequestInterface $request): ?AuthenticatedAccountContext
    {
        $context = $request->getAttribute(AuthenticationAttributes::ACCOUNT);

        return $context instanceof AuthenticatedAccountContext ? $context : null;
    }

    public function rejection(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->fragments->isFragment($request)) {
            return $this->json->createProblem(
                ['type' => 'about:blank', 'title' => 'Authentication required', 'status' => 401,
                    'code' => 'AUTHENTICATION_REQUIRED'],
                401,
                ['X-QMDB-Navigate' => '/login'],
            );
        }

        return $this->responses->createResponse(303)
            ->withHeader('Location', '/login')
            ->withHeader('Cache-Control', 'no-store');
    }
}
