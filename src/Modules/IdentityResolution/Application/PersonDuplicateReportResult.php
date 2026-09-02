<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class PersonDuplicateReportResult
{
    public function __construct(public string $casePublicId, public string $status, public bool $replayed)
    {
    }
}
