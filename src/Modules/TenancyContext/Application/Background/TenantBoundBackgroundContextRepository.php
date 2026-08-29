<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Background;

interface TenantBoundBackgroundContextRepository
{
    public function resolve(AccountTenantBoundBackgroundJob $job): ?TenantBoundBackgroundJobExecutionContext;
}
