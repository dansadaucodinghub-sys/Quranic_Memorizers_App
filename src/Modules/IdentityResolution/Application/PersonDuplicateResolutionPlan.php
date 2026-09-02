<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class PersonDuplicateResolutionPlan
{
    /** @param list<string> $conflictCodes */
    public function __construct(public array $conflictCodes, public int $affectedRecordCount)
    {
    }

    public function isSafe(): bool
    {
        return $this->conflictCodes === [];
    }
}
