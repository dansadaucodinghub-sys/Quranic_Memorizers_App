<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

interface TenantContextSchemaVerifier
{
    public function verify(): TenantContextVerificationReport;
}
