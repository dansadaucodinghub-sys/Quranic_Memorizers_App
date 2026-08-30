<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

final readonly class SecurityAuditControlVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
