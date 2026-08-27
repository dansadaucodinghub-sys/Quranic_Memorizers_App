<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application\Mail;

final readonly class PasswordRecoveryMessage
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $textBody,
        public string $htmlBody,
    ) {
    }
}
