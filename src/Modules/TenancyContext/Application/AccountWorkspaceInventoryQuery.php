<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;

final readonly class AccountWorkspaceInventoryQuery
{
    public int $pageSize;

    public function __construct(
        public AuthenticatedAccountContext $account,
        int $pageSize = 25,
        public ?WorkspaceId $afterWorkspaceId = null,
    ) {
        if ($pageSize < 1 || $pageSize > 50) {
            throw new \InvalidArgumentException('Workspace inventory page size is invalid.');
        }
        $this->pageSize = $pageSize;
    }
}
