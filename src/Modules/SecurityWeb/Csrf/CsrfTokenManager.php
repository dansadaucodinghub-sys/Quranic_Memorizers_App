<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use DateTimeImmutable;

interface CsrfTokenManager
{
    public function issue(CsrfAction $action, CsrfCookieNonce $nonce, DateTimeImmutable $now): CsrfToken;

    public function verify(
        CsrfAction $action,
        CsrfCookieNonce $nonce,
        CsrfToken $token,
        DateTimeImmutable $now,
    ): CsrfTokenVerificationResult;
}
