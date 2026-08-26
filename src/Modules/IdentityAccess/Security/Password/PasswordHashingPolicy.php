<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

final readonly class PasswordHashingPolicy
{
    /** @param array{memory_cost?: int, time_cost?: int, threads?: int} $options */
    public function __construct(public array $options = [], public int $metadataVersion = 1)
    {
        if (!defined('PASSWORD_ARGON2ID') || $metadataVersion < 1) {
            throw new \RuntimeException('Argon2id password hashing is unavailable or invalid.');
        }
    }
}
