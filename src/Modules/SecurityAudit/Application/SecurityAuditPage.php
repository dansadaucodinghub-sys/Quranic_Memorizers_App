<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

final readonly class SecurityAuditPage
{
    /** @param list<SecurityAuditEventRecord> $events */
    public function __construct(public array $events, public ?string $nextCursor)
    {
    }
}
