<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\RateLimit;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;

final readonly class IdentityRateLimitAttempt
{
    public function __construct(
        public IdentityRateLimitScope $scope,
        public IdentityFingerprint $fingerprint,
        public IdentityRateLimitPolicy $policy,
    ) {
    }
}
