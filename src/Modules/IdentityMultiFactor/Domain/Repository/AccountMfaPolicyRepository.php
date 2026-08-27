<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicy;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPreferredMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;

interface AccountMfaPolicyRepository
{
    public function findPolicy(int $accountInternalId, bool $forUpdate = false): AccountMfaPolicy;

    /** @return list<AuthenticationMethod> */
    public function activeMethods(int $accountInternalId): array;

    public function activeStrongFactorCount(int $accountInternalId): int;

    public function enablePolicy(
        AccountMfaPolicy $policy,
        AccountMfaPreferredMethod $preferredMethod,
        DateTimeImmutable $now,
    ): bool;

    public function disablePolicy(AccountMfaPolicy $policy, DateTimeImmutable $now): bool;
}
