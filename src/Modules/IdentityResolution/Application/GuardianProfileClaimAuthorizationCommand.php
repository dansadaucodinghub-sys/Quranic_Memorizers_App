<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class GuardianProfileClaimAuthorizationCommand
{
    public function __construct(
        public string $dependentPersonPublicId,
        public string $pairingCode,
        public IdentityResolutionSubmissionId $submission,
    ) {
    }
}
