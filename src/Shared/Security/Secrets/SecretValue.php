<?php

declare(strict_types=1);

namespace Qmdb\Shared\Security\Secrets;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class SecretValue implements JsonSerializable
{
    private const REDACTED = '[REDACTED]';

    public function __construct(#[SensitiveParameter] private string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Secret value must not be empty.');
        }
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function hasLengthAtLeast(int $minimumLength): bool
    {
        if ($minimumLength < 1) {
            throw new InvalidArgumentException('Minimum secret length must be positive.');
        }

        return mb_strlen($this->value, '8bit') >= $minimumLength;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }

    public function jsonSerialize(): string
    {
        return self::REDACTED;
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Secret values cannot be serialized.');
    }

    /** @param array<never, never> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Secret values cannot be unserialized.');
    }
}
