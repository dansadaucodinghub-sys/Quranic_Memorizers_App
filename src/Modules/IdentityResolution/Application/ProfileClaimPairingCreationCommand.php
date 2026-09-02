<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class ProfileClaimPairingCreationCommand
{
    public function __construct(public IdentityResolutionSubmissionId $submission)
    {
    }
}
