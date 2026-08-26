<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;

final readonly class SessionTokenSecret implements JsonSerializable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Session token secret is invalid.');
        }
    }

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function revealForCookie(): string
    {
        return $this->value;
    }

    public function hash(): SessionTokenHash
    {
        return new SessionTokenHash(hash('sha256', $this->value, true));
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Session token secrets cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Session token secrets cannot be serialized.');
    }
}
