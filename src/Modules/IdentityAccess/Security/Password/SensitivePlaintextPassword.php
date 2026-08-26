<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class SensitivePlaintextPassword implements JsonSerializable
{
    public function __construct(#[SensitiveParameter] private string $value)
    {
    }

    public function revealForPolicy(): string
    {
        return $this->value;
    }

    public function revealForHashing(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Plaintext passwords cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Plaintext passwords cannot be serialized.');
    }

    /** @param array<never, never> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Plaintext passwords cannot be unserialized.');
    }
}
