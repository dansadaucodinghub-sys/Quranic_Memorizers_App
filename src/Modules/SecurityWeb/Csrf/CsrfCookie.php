<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

final readonly class CsrfCookie
{
    public function __construct(public CsrfCookieNonce $nonce, public ?string $setCookieHeader)
    {
    }
}
