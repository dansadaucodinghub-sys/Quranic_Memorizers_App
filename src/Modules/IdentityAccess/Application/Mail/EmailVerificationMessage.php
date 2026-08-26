<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Mail;

final readonly class EmailVerificationMessage
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $textBody,
        public string $htmlBody,
    ) {
    }
}
