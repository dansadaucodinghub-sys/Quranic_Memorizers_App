<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use Qmdb\Modules\Identity\Domain\Value\AccountId;

final readonly class VerifiedAccountPrincipal
{
    public function __construct(public int $accountInternalId, public AccountId $accountId)
    {
    }
}
