<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;

final readonly class RegistrationFormInput
{
    public function __construct(
        public string $email,
        public SensitivePlaintextPassword $password,
        public string $confirmation,
        public string $csrfToken,
        public RegistrationSubmissionId $submissionId,
    ) {
    }
}
