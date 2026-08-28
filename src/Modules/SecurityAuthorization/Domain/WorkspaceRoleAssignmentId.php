<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use Qmdb\Shared\Identifier\UuidV7;
use Stringable;

final readonly class WorkspaceRoleAssignmentId implements Stringable
{
    private function __construct(private UuidV7 $value)
    {
    }

    public static function generate(): self
    {
        return new self(UuidV7::generate());
    }

    public static function fromString(string $value): self
    {
        return new self(UuidV7::fromString($value));
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

    public function __toString(): string
    {
        return $this->toString();
    }
}
