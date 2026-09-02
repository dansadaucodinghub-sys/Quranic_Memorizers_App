<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class PersonDuplicateConsentResult
{
    public function __construct(public string $casePublicId, public string $status, public bool $authoritiesRegenerated, public bool $replayed)
    {
    }
}
