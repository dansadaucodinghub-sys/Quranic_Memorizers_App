<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class ProfileVerificationRevocationCommand
{
    public function __construct(public string $personPublicId, public string $assertionPublicId, public int $expectedVersion, public IdentityResolutionSubmissionId $submission)
    {
    }
}
