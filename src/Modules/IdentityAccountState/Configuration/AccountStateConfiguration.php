<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Configuration;

final readonly class AccountStateConfiguration
{
    public function __construct(
        public int $justificationMaximumBytes,
        public int $referenceMaximumBytes,
        public int $rateLimitWindowSeconds,
        public int $rateLimitMaximumAttempts,
    ) {
    }
}
