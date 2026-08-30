<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

interface AccountStateHttpRepository
{
    /** @return array{public_id:string,status:string,version:int,created_at:string,updated_at:string,activated_at:?string,suspended_at:?string}|null */
    public function findSafeAccount(string $accountPublicId): ?array;
}
