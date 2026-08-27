<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use SensitiveParameter;

final readonly class WebAuthnChallenge
{
    public function __construct(#[SensitiveParameter] private string $bytes)
    {
        if (strlen($bytes) < 32) {
            throw new \InvalidArgumentException('WebAuthn challenge must contain at least 32 bytes.');
        }
    }

    public static function generate(): self
    {
        return new self(random_bytes(32));
    }

    public static function fromBase64Url(string $value): self
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if (!is_string($decoded)) {
            throw new \InvalidArgumentException('WebAuthn challenge is malformed.');
        }

        return new self($decoded);
    }

    public function toBase64Url(): string
    {
        return rtrim(strtr(base64_encode($this->bytes), '+/', '-_'), '=');
    }

    public function hash(): string
    {
        return hash('sha256', $this->bytes, true);
    }

    public function revealForVerification(): string
    {
        return $this->bytes;
    }

    public function matchesHash(string $hash): bool
    {
        return strlen($hash) === 32 && hash_equals($hash, $this->hash());
    }

    /** @return array{challenge: string} */
    public function __debugInfo(): array
    {
        return ['challenge' => '[REDACTED]'];
    }
}
