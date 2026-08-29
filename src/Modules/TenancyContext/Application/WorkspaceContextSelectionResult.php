<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;

final readonly class WorkspaceContextSelectionResult
{
    public function __construct(
        public AccountWorkspaceTenantContext $context,
        public bool $changed,
    ) {
    }
}
