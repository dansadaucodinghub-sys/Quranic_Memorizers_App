<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

interface SecurityAuditControlVerifier
{
    public function verifyControls(): SecurityAuditControlVerificationReport;
}
