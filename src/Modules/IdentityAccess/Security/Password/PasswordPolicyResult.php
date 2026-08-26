<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

final readonly class PasswordPolicyResult
{
    /** @param list<PasswordPolicyViolation> $violations */
    public function __construct(private array $violations)
    {
    }

    public function accepted(): bool
    {
        return $this->violations === [];
    }

    /** @return list<PasswordPolicyViolation> */
    public function violations(): array
    {
        return $this->violations;
    }
}
