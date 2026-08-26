<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use RuntimeException;

final readonly class DummyPasswordHashProvider
{
    private SensitivePasswordHash $hash;

    public function __construct(PasswordHashingPolicy $policy)
    {
        $source = base64_encode(random_bytes(32));
        try {
            $hash = password_hash($source, PASSWORD_ARGON2ID, $policy->options);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Dummy password hash generation failed safely.', 0, $exception);
        }
        $this->hash = new SensitivePasswordHash($hash);
    }

    public function hash(): SensitivePasswordHash
    {
        return $this->hash;
    }
}
