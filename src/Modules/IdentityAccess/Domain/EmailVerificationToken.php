<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;

final readonly class EmailVerificationToken implements JsonSerializable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Email-verification token is invalid.');
        }
    }

    public function revealForProof(): string
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
        throw new LogicException('Email-verification tokens cannot be serialized.');
    }
}
