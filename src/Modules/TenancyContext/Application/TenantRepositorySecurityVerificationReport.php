<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

final readonly class TenantRepositorySecurityVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(
        public int $tenantRepositoryCount,
        public int $tenantRepositoryMethodCount,
        public int $explicitGlobalRepositoryCount,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
