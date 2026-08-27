<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;

final readonly class PasswordResetFormInput
{
    public function __construct(
        public PasswordRecoveryToken $token,
        public SensitivePlaintextPassword $password,
        public string $confirmation,
        public string $csrfToken,
        public PasswordResetSubmissionId $submissionId,
    ) {
    }
}
