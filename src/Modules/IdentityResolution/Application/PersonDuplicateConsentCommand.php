<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateConsentDecision;

final readonly class PersonDuplicateConsentCommand
{
    public function __construct(
        public string $requirementPublicId,
        public PersonDuplicateConsentDecision $decision,
        public int $expectedVersion,
        public IdentityResolutionSubmissionId $submission,
    ) {
    }
}
