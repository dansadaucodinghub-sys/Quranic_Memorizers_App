<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class PersonDuplicateResolutionResult
{
    /** @param list<string> $conflictCodes */
    public function __construct(public string $casePublicId, public string $status, public ?string $canonicalPersonPublicId, public array $conflictCodes = [], public bool $replayed = false)
    {
    }
}
