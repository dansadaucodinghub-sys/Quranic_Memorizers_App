<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

final readonly class ProfileClaimAuthorizationResult
{
    public function __construct(public string $claimPublicId, public bool $replayed)
    {
    }
}
