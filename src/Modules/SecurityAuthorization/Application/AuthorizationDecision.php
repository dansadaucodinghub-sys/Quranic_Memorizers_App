<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionOutcome;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;

final readonly class AuthorizationDecision
{
    private function __construct(
        public AuthorizationDecisionOutcome $outcome,
        public AuthorizationDecisionReason $reason,
    ) {
    }

    public static function allow(): self
    {
        return new self(AuthorizationDecisionOutcome::ALLOW, AuthorizationDecisionReason::ALLOWED);
    }

    public static function deny(AuthorizationDecisionReason $reason): self
    {
        if ($reason === AuthorizationDecisionReason::ALLOWED) {
            throw new \InvalidArgumentException('A denied decision requires a denial reason.');
        }

        return new self(AuthorizationDecisionOutcome::DENY, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->outcome === AuthorizationDecisionOutcome::ALLOW;
    }
}
