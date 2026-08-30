<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

final readonly class AccountStateOperationResult
{
    public function __construct(public string $operationPublicId, public string $targetAccountPublicId, public int $accountVersion)
    {
    }
}
