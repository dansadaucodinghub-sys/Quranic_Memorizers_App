<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain\Value;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class SensitivePasswordHash implements JsonSerializable
{
    public function __construct(#[SensitiveParameter] private string $value)
    {
        if ($value === '' || strlen($value) > 255 || password_get_info($value)['algoName'] === 'unknown') {
            throw new InvalidArgumentException('Password hash is invalid.');
        }
    }

    public function revealForVerification(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Password hashes cannot be serialized.');
    }
}
