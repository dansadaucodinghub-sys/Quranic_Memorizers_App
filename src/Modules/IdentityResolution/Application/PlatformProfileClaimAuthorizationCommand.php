<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class PlatformProfileClaimAuthorizationCommand
{
    public function __construct(
        public string $personRegistryCode,
        public string $pairingCode,
        public string $reviewReference,
        public string $reviewJustification,
        public IdentityResolutionSubmissionId $submission,
    ) {
    }
}
