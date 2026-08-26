<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use RuntimeException;

final readonly class Argon2IdPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordPolicy $passwordPolicy, private PasswordHashingPolicy $hashingPolicy)
    {
    }

    public function hash(SensitivePlaintextPassword $password, string $confirmation): PasswordHashResult
    {
        if (!$this->passwordPolicy->evaluate($password, $confirmation)->accepted()) {
            throw new \InvalidArgumentException('Password policy validation failed.');
        }
        try {
            $hash = password_hash($password->revealForHashing(), PASSWORD_ARGON2ID, $this->hashingPolicy->options);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Password hashing failed safely.', 0, $exception);
        }

        return new PasswordHashResult(
            new SensitivePasswordHash($hash),
            'argon2id',
            $this->hashingPolicy->metadataVersion,
        );
    }
}
