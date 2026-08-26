<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Qmdb\Modules\IdentityAccess\Domain\VerificationResendSubmissionId;

final readonly class ResendFormInput
{
    public function __construct(
        public string $email,
        public string $csrfToken,
        public VerificationResendSubmissionId $submissionId,
    ) {
    }
}
