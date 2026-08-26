<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;

final readonly class VerificationFormInput
{
    public function __construct(public EmailVerificationToken $token, public string $csrfToken)
    {
    }
}
