<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryRequestSubmissionId;

final readonly class PasswordRecoveryRequestCommand
{
    public function __construct(
        public string $email,
        public PasswordRecoveryRequestSubmissionId $submissionId,
        public DirectPeerAddress $peer,
        public string $locale,
        public ?string $correlationId = null,
    ) {
    }
}
