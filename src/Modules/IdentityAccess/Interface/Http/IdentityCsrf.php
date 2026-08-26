<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieFactory;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Modules\SecurityWeb\Csrf\SameOriginMutationValidator;
use Qmdb\Shared\Time\Clock;

final readonly class IdentityCsrf
{
    public function __construct(
        private CsrfCookieFactory $cookies,
        private CsrfTokenManager $tokens,
        private SameOriginMutationValidator $sameOrigin,
        private Clock $clock,
    ) {
    }

    /** @return array{cookie: CsrfCookie, token: string} */
    public function issue(ServerRequestInterface $request, CsrfAction $action): array
    {
        $cookie = $this->cookies->resolve($request);

        return [
            'cookie' => $cookie,
            'token' => $this->tokens->issue($action, $cookie->nonce, $this->clock->now())->value(),
        ];
    }

    /** @return array{cookie: CsrfCookie, token: string} */
    public function rotate(CsrfAction $action): array
    {
        $cookie = $this->cookies->fresh();

        return [
            'cookie' => $cookie,
            'token' => $this->tokens->issue($action, $cookie->nonce, $this->clock->now())->value(),
        ];
    }

    public function issueForCookie(CsrfCookie $cookie, CsrfAction $action): string
    {
        return $this->tokens->issue($action, $cookie->nonce, $this->clock->now())->value();
    }

    public function validates(
        ServerRequestInterface $request,
        CsrfAction $action,
        CsrfCookie $cookie,
        string $formToken,
    ): bool {
        $headerToken = trim($request->getHeaderLine('X-QMDB-CSRF'));
        $submitted = $headerToken === '' ? $formToken : $headerToken;

        return $this->sameOrigin->validate($request, $action, $cookie->nonce, $submitted, $this->clock->now());
    }
}
