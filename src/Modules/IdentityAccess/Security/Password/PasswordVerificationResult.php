<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

final readonly class PasswordVerificationResult
{
    public function __construct(public bool $verified, public bool $rehashRequired)
    {
    }
}
