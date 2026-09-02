<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

interface PersonVerificationProjectionProvider
{
    /** @return list<string> Active QMDB record-status assertion types for a canonical identity group. */
    public function activeAssertionTypes(int $personId): array;
}
