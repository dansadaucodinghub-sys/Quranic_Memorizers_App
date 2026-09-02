<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class ProfileClaimMaintenanceResult
{
    public function __construct(public int $expiredPairings, public int $expiredClaims)
    {
    }
}
