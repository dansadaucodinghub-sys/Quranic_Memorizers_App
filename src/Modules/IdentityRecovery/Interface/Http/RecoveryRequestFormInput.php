<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryRequestSubmissionId;

final readonly class RecoveryRequestFormInput
{
    public function __construct(
        public string $email,
        public string $csrfToken,
        public PasswordRecoveryRequestSubmissionId $submissionId,
    ) {
    }
}
