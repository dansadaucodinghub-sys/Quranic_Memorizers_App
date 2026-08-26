<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieValue;

final readonly class SessionCookieFactory
{
    public function __construct(private IdentitySessionConfiguration $configuration)
    {
    }

    public function name(): string
    {
        return $this->configuration->sessionCookieName;
    }

    public function issue(SessionCookieValue $value): AuthenticationCookieInstruction
    {
        return new AuthenticationCookieInstruction(
            $this->name() . '=' . $value->revealForCookie()
            . '; Path=/; HttpOnly; SameSite=Lax'
            . ($this->configuration->productionLike ? '; Secure' : ''),
        );
    }

    public function clear(): AuthenticationCookieInstruction
    {
        return new AuthenticationCookieInstruction(
            $this->name() . '=; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax'
            . ($this->configuration->productionLike ? '; Secure' : ''),
        );
    }
}
