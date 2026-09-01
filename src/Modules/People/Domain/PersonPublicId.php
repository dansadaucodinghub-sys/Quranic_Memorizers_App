<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

use InvalidArgumentException;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class PersonPublicId
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
        return new self(UuidV7::fromString(strtolower($value)));
    }

    public static function fromBinary(string $value): self
    {
        if (strlen($value) !== 16) {
            throw new InvalidArgumentException('Person public identifier is invalid.');
        }

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
