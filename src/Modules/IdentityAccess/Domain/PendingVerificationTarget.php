<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

final readonly class PendingVerificationTarget
{
    public function __construct(public int $accountInternalId, public int $emailInternalId)
    {
    }
}
