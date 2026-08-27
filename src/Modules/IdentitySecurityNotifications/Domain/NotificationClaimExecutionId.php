<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

use Qmdb\Shared\Identifier\UuidV7;

final readonly class NotificationClaimExecutionId
{
    private function __construct(private UuidV7 $value)
    {
    }

    public static function generate(): self
    {
        return new self(UuidV7::generate());
    }

    public static function fromBinary(string $value): self
    {
        return new self(UuidV7::fromBinary($value));
    }

    public function toBinary(): string
    {
        return $this->value->toBinary();
    }

    public function toString(): string
    {
        return $this->value->toString();
    }
}
