<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use JsonSerializable;
use LogicException;
use ParagonIE\ConstantTime\Base32;
use SensitiveParameter;

final readonly class TotpSecret implements JsonSerializable
{
    public function __construct(#[SensitiveParameter] private string $encoded)
    {
        if (preg_match('/\A[A-Z2-7]{32,128}\z/', $encoded) !== 1) {
            throw new \InvalidArgumentException('TOTP secret is invalid.');
        }
    }

    public static function generate(): self
    {
        return new self(Base32::encodeUpperUnpadded(random_bytes(20)));
    }

    public function revealForEncryption(): string
    {
        return $this->encoded;
    }

    /** @return non-empty-string */
    public function revealForTotp(): string
    {
        if ($this->encoded === '') {
            throw new LogicException('TOTP secret is unavailable.');
        }

        return $this->encoded;
    }

    public function revealForEnrollment(): string
    {
        return $this->encoded;
    }

    /** @return array{secret: string} */
    public function __debugInfo(): array
    {
        return ['secret' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('TOTP secret cannot be serialized.');
    }

    public function __serialize(): never
    {
        throw new LogicException('TOTP secret cannot be serialized.');
    }
}
