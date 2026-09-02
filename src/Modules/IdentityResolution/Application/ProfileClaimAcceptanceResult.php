<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class ProfileClaimAcceptanceResult
{
    public function __construct(public string $personPublicId, public bool $replayed)
    {
    }
}
