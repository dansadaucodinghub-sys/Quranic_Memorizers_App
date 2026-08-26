<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Registration;

use Qmdb\Modules\IdentityAccess\Domain\RegistrationSubmissionId;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;

final readonly class AccountRegistrationCommand
{
    public function __construct(
        public string $email,
        public SensitivePlaintextPassword $password,
        public string $passwordConfirmation,
        public RegistrationSubmissionId $submissionId,
        public DirectPeerAddress $peer,
        public string $locale,
    ) {
    }
}
