<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Mail;

interface EmailVerificationNotifier
{
    public function send(EmailVerificationMessage $message): void;
}
