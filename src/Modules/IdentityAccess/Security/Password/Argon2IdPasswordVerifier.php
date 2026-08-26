<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

final readonly class Argon2IdPasswordVerifier implements PasswordVerifier
{
    public function __construct(private PasswordHashingPolicy $policy)
    {
    }

    public function verify(
        SensitivePlaintextPassword $password,
        SensitivePasswordHash $hash,
    ): PasswordVerificationResult {
        $stored = $hash->revealForVerification();
        $verified = password_verify($password->revealForHashing(), $stored);

        return new PasswordVerificationResult(
            $verified,
            $verified && password_needs_rehash($stored, PASSWORD_ARGON2ID, $this->policy->options),
        );
    }
}
