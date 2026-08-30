<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

final readonly class SecurityAuditAppendResult
{
    public function __construct(public string $eventPublicId, public string $streamPublicId, public int $sequenceNumber)
    {
    }
}
