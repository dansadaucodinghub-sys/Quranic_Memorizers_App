<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Infrastructure\Security;

use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenGenerator;

final readonly class SecureEmailVerificationTokenGenerator implements EmailVerificationTokenGenerator
{
    public function generate(): EmailVerificationToken
    {
        return new EmailVerificationToken(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }
}
