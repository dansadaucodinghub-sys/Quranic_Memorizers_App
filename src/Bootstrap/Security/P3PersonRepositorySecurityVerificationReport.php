<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Security;

final readonly class P3PersonRepositorySecurityVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(public int $repositoryCount, public int $scopeCheckCount, public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
