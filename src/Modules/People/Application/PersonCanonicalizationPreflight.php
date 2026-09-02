<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

final readonly class PersonCanonicalizationPreflight
{
    /** @param list<string> $conflictCodes */
    public function __construct(public array $conflictCodes = [], public int $affectedRecordCount = 0)
    {
    }

    public function isSafe(): bool
    {
        return $this->conflictCodes === [];
    }
}
