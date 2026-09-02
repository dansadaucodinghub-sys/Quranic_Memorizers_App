<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

interface PersonCanonicalIdentityResolver
{
    /** Resolves a canonical internal identity only after the caller has established access. */
    public function resolve(int $personId): int;

    public function group(int $personId): PersonCanonicalIdentityGroup;
}
