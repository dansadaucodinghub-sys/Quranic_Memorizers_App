<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

final readonly class AuthenticationAssuranceComparator
{
    public function satisfies(
        AuthenticationAssuranceLevel $actual,
        AuthenticationAssuranceLevel $required,
    ): bool {
        return $this->strength($actual) >= $this->strength($required);
    }

    private function strength(AuthenticationAssuranceLevel $level): int
    {
        return match ($level) {
            AuthenticationAssuranceLevel::PRIMARY => 1,
            AuthenticationAssuranceLevel::MULTI_FACTOR => 2,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT => 3,
        };
    }
}
