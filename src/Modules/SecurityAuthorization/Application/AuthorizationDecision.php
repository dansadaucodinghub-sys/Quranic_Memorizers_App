<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionOutcome;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionSource;

final readonly class AuthorizationDecision
{
    private function __construct(
        public AuthorizationDecisionOutcome $outcome,
        public AuthorizationDecisionReason $reason,
        public ?AuthorizationDecisionSource $source,
    ) {
    }

    public static function allow(AuthorizationDecisionSource $source = AuthorizationDecisionSource::ROLE_ASSIGNMENT): self
    {
        return new self(AuthorizationDecisionOutcome::ALLOW, AuthorizationDecisionReason::ALLOWED, $source);
    }

    public static function deny(AuthorizationDecisionReason $reason): self
    {
        if ($reason === AuthorizationDecisionReason::ALLOWED) {
            throw new \InvalidArgumentException('A denied decision requires a denial reason.');
        }

        return new self(AuthorizationDecisionOutcome::DENY, $reason, null);
    }

    public function isAllowed(): bool
    {
        return $this->outcome === AuthorizationDecisionOutcome::ALLOW;
    }
}
