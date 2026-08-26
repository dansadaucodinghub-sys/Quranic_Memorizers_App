<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

use Qmdb\Modules\IdentityAccess\Domain\VerificationResendSubmissionId;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;

final readonly class EmailVerificationResendCommand
{
    public function __construct(
        public string $email,
        public VerificationResendSubmissionId $submissionId,
        public DirectPeerAddress $peer,
        public string $locale,
    ) {
    }
}
