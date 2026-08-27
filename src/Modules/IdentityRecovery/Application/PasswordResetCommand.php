<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;

final readonly class PasswordResetCommand
{
    public function __construct(
        public PasswordRecoveryChallengeId $challengeId,
        public PasswordRecoveryToken $token,
        public SensitivePlaintextPassword $password,
        public string $passwordConfirmation,
        public PasswordResetSubmissionId $submissionId,
        public DirectPeerAddress $peer,
        public ?string $correlationId = null,
    ) {
    }
}
