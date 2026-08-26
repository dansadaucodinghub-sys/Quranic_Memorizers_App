<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;

final readonly class AccountLoginCommand
{
    public function __construct(
        public string $email,
        public SensitivePlaintextPassword $password,
        public LoginSubmissionId $submissionId,
        public DirectPeerAddress $peer,
        public ?string $deviceCookie,
        public ?AuthenticatedAccountContext $currentContext,
    ) {
    }
}
