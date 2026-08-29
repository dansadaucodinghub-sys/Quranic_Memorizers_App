<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

interface PrivilegedAccessSchemaVerifier
{
    public function verify(): PrivilegedAccessVerificationReport;
}
