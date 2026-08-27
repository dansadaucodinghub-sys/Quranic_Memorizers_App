<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;

interface WebAuthnUserHandleRepository
{
    public function findOrCreateUserHandle(int $accountInternalId, DateTimeImmutable $now): string;

    public function findAccountByUserHandle(string $userHandle): ?int;
}
