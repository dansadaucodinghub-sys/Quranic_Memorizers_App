<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application\Mail;

interface PasswordRecoveryNotifier
{
    public function send(PasswordRecoveryMessage $message): void;
}
