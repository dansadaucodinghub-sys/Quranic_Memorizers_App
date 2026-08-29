<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

final readonly class TenantContextVerificationReport
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
