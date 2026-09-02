<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use DateTimeImmutable;

interface IdentityResolutionRepository
{
    /** @return array{active_pairings:int,pending_claims:int,accepted_claims:int,active_assertions:int,open_cases:int,blocked_cases:int,resolved_cases:int,aliases:int,invalid_rows:int} */
    public function report(): array;

    public function expirePairings(DateTimeImmutable $now, int $limit): int;

    public function expireClaims(DateTimeImmutable $now, int $limit): int;
}
