<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;

final readonly class PersonDuplicateReportCommand
{
    public function __construct(
        public ?string $managedPersonPublicId,
        public string $otherPersonRegistryCode,
        public IdentityResolutionSubmissionId $submission,
    ) {
    }
}
