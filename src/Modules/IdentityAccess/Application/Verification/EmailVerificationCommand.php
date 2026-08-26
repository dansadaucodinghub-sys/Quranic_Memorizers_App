<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Verification;

use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;

final readonly class EmailVerificationCommand
{
    public function __construct(
        public EmailVerificationChallengeId $challengeId,
        public EmailVerificationToken $token,
        public DirectPeerAddress $peer,
    ) {
    }
}
