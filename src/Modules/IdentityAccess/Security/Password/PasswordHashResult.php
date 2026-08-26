<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

final readonly class PasswordHashResult
{
    public function __construct(
        public SensitivePasswordHash $hash,
        public string $algorithm,
        public int $metadataVersion,
    ) {
    }
}
