<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

interface SecurityAuditIntegrityKeyProvider
{
    public function keyForVersion(int $version): string;
}
