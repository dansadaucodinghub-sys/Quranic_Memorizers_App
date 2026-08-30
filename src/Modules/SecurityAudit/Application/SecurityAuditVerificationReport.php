<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

final readonly class SecurityAuditVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(public int $streamCount, public int $eventCount, public int $checkpointCount, public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
