<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class PersonDuplicateResolutionCommand
{
    public function __construct(
        public string $casePublicId,
        public string $canonicalPersonPublicId,
        public string $duplicatePersonPublicId,
        public int $expectedVersion,
        public string $reference,
        public string $justification,
        public IdentityResolutionSubmissionId $submission,
    ) {
    }
}
