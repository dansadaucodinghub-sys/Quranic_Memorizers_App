<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

use InvalidArgumentException;

final readonly class PasswordRecoveryTokenHash
{
    public function __construct(private string $value)
    {
        if (strlen($value) !== 32) {
            throw new InvalidArgumentException('Password recovery token hash must be 32 bytes.');
        }
    }

    public static function fromToken(PasswordRecoveryToken $token): self
    {
        return new self(hash('sha256', $token->revealForProof(), true));
    }

    public function matches(PasswordRecoveryToken $token): bool
    {
        return hash_equals($this->value, hash('sha256', $token->revealForProof(), true));
    }

    public function toBinary(): string
    {
        return $this->value;
    }
}
