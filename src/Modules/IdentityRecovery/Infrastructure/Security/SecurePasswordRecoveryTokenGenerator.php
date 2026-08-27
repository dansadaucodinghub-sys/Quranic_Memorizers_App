<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Infrastructure\Security;

use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenGenerator;

final readonly class SecurePasswordRecoveryTokenGenerator implements PasswordRecoveryTokenGenerator
{
    public function generate(): PasswordRecoveryToken
    {
        return new PasswordRecoveryToken(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }
}
