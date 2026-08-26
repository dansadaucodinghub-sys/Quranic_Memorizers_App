<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Shared\Time\Clock;

final readonly class AccountLogoutService
{
    public function __construct(
        private UserSessionRepository $sessions,
        private SessionCookieFactory $cookies,
        private Clock $clock,
    ) {
    }

    public function logout(?AuthenticatedAccountContext $context): AuthenticationCookieInstruction
    {
        if ($context !== null) {
            $this->sessions->revokeCurrent(
                $context->accountInternalId,
                $context->sessionInternalId,
                SessionRevocationReason::USER_LOGOUT,
                $this->clock->now(),
            );
        }

        return $this->cookies->clear();
    }
}
