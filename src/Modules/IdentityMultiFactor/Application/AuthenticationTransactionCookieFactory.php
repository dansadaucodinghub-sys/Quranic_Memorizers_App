<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieInstruction;

final readonly class AuthenticationTransactionCookieFactory
{
    public function __construct(private IdentityMultiFactorConfiguration $configuration)
    {
    }

    public function name(): string
    {
        return $this->configuration->productionLike ? '__Host-qmdb_auth_tx' : 'qmdb_auth_tx';
    }

    public function issue(AuthenticationTransactionCookieValue $value): AuthenticationCookieInstruction
    {
        return new AuthenticationCookieInstruction(
            $this->name() . '=' . $value->revealForCookie()
            . '; Path=/; Max-Age=' . $this->configuration->transactionTtlSeconds
            . '; HttpOnly; SameSite=Lax'
            . ($this->configuration->productionLike ? '; Secure' : ''),
        );
    }

    public function clear(): AuthenticationCookieInstruction
    {
        return new AuthenticationCookieInstruction(
            $this->name() . '=; Path=/; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; '
            . 'HttpOnly; SameSite=Lax'
            . ($this->configuration->productionLike ? '; Secure' : ''),
        );
    }
}
