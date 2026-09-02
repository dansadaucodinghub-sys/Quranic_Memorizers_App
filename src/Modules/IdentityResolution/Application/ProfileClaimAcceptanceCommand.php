<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class ProfileClaimAcceptanceCommand
{
    public function __construct(public string $claimPublicId, public int $expectedVersion, public IdentityResolutionSubmissionId $submission)
    {
    }
}
