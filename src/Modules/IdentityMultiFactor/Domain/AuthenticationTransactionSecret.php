<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class AuthenticationTransactionSecret implements JsonSerializable
{
    public function __construct(#[SensitiveParameter] private string $bytes)
    {
        if (strlen($bytes) !== 32) {
            throw new \InvalidArgumentException('Authentication transaction secret must contain 32 bytes.');
        }
    }

    public static function generate(): self
    {
        return new self(random_bytes(32));
    }

    public static function fromEncoded(string $encoded): self
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if (!is_string($decoded)) {
            throw new \InvalidArgumentException('Authentication transaction secret is malformed.');
        }

        return new self($decoded);
    }

    public function revealForCookie(): string
    {
        return rtrim(strtr(base64_encode($this->bytes), '+/', '-_'), '=');
    }

    public function hash(): AuthenticationTransactionSecretHash
    {
        return new AuthenticationTransactionSecretHash(hash('sha256', $this->bytes, true));
    }

    /** @return array{secret: string} */
    public function __debugInfo(): array
    {
        return ['secret' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Authentication transaction secret cannot be serialized.');
    }

    public function __serialize(): never
    {
        throw new LogicException('Authentication transaction secret cannot be serialized.');
    }
}
