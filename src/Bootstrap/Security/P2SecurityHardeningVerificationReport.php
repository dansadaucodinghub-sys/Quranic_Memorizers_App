<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Security;

final readonly class P2SecurityHardeningVerificationReport
{
    /** @param array<string, bool> $components
     * @param list<string> $errors
     */
    public function __construct(public array $components, public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
