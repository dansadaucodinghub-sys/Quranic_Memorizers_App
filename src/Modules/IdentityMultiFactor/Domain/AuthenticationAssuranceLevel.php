<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

enum AuthenticationAssuranceLevel: string
{
    case PRIMARY = 'PRIMARY';
    case MULTI_FACTOR = 'MULTI_FACTOR';
    case PHISHING_RESISTANT = 'PHISHING_RESISTANT';

    public function satisfies(self $required): bool
    {
        return $this->strength() >= $required->strength();
    }

    private function strength(): int
    {
        return match ($this) {
            self::PRIMARY => 1,
            self::MULTI_FACTOR => 2,
            self::PHISHING_RESISTANT => 3,
        };
    }
}
