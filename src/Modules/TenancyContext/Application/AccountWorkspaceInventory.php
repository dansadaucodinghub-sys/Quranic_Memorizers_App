<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class AccountWorkspaceInventory
{
    /** @param list<AccountWorkspaceInventoryItem> $items */
    public function __construct(
        public TenantContextVersion $version,
        public ?AccountWorkspaceTenantContext $current,
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
