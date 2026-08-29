<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Export;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;

interface TenantScopedExportRequest
{
    public function tenantContext(): AccountWorkspaceTenantContext;
}
