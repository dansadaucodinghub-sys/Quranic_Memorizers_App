<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

use InvalidArgumentException;

final readonly class EmailVerificationTokenHash
{
    public function __construct(private string $binary)
    {
        if (strlen($binary) !== 32) {
            throw new InvalidArgumentException('Email-verification token hash is invalid.');
        }
    }

    public static function fromToken(EmailVerificationToken $token): self
    {
        return new self(hash('sha256', $token->revealForProof(), true));
    }

    public function toBinary(): string
    {
        return $this->binary;
    }

    public function matches(EmailVerificationToken $token): bool
    {
        return hash_equals($this->binary, hash('sha256', $token->revealForProof(), true));
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
