<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

final readonly class PersonProfileAccessDecision
{
    private function __construct(public bool $allowed, public ?PersonProfileAccessReason $reason)
    {
    }

    public static function allow(): self
    {
        return new self(true, null);
    }

    public static function deny(PersonProfileAccessReason $reason): self
    {
        return new self(false, $reason);
    }
}
