<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class WorkspaceContextClearingResult
{
    public function __construct(
        public TenantContextVersion $version,
        public bool $changed,
    ) {
    }
}
