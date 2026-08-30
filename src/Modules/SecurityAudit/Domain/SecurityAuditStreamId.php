<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use Qmdb\Shared\Identifier\UuidV7;

/**
 * Public stream identity. Internal database keys are intentionally not exposed by this value object.
 */
final readonly class SecurityAuditStreamId
{
    public function __construct(private UuidV7 $value)
    {
    }

    public static function fromString(string $value): self
    {
        return new self(UuidV7::fromString($value));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function value(): UuidV7
    {
        return $this->value;
    }
}
