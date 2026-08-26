<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

interface PasswordVerifier
{
    public function verify(
        SensitivePlaintextPassword $password,
        SensitivePasswordHash $hash,
    ): PasswordVerificationResult;
}
