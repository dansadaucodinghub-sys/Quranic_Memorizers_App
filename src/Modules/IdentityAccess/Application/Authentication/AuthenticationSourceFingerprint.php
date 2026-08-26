<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;

final readonly class AuthenticationSourceFingerprint
{
    public function __construct(public IdentityFingerprint $email, public IdentityFingerprint $peer)
    {
    }
}
