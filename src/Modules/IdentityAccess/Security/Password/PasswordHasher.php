<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

interface PasswordHasher
{
    public function hash(SensitivePlaintextPassword $password, string $confirmation): PasswordHashResult;
}
