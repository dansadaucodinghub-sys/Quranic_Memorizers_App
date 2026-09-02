<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class PersonDuplicateDismissalCommand
{
    public function __construct(public string $casePublicId, public int $expectedVersion, public string $reference, public string $justification, public IdentityResolutionSubmissionId $submission)
    {
    }
}
