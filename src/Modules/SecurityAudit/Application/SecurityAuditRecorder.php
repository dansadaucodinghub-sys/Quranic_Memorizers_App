<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

interface SecurityAuditRecorder
{
    public function append(SecurityAuditAppendCommand $command): SecurityAuditAppendResult;
}
