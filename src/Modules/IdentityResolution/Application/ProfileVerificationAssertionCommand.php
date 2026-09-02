<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class ProfileVerificationAssertionCommand
{
    public function __construct(public string $personReference, public string $reference, public string $justification, public IdentityResolutionSubmissionId $submission)
    {
    }
}
