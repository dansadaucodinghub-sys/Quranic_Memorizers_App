<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class TenantContextResolution
{
    public function __construct(
        public TenantContextVersion $version,
        public ?AccountWorkspaceTenantContext $context,
        public bool $invalidSelectionCleared = false,
    ) {
    }
}
