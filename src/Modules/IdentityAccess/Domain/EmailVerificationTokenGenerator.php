<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

interface EmailVerificationTokenGenerator
{
    public function generate(): EmailVerificationToken;
}
