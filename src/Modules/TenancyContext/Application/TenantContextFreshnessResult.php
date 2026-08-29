<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class TenantContextFreshnessResult
{
    public function __construct(
        public TenantContextVersion $authoritativeVersion,
        public bool $clientVersionProvided,
    ) {
    }

    public function isFresh(): bool
    {
        return true;
    }
}
