<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

final readonly class PrivilegedAccessVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(
        public int $policyCount,
        public int $requestCount,
        public int $activationCount,
        public int $reviewCount,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
