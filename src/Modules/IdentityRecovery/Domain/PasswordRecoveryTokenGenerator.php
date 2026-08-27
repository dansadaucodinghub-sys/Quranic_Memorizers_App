<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

interface PasswordRecoveryTokenGenerator
{
    public function generate(): PasswordRecoveryToken;
}
