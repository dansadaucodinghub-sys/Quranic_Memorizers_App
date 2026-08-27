<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeSet;

interface RecoveryCodeSetRepository
{
    /** @param list<RecoveryCodeHash> $hashes */
    public function replaceRecoveryCodeSet(
        int $accountInternalId,
        array $hashes,
        DateTimeImmutable $now,
    ): RecoveryCodeSet;

    public function findActiveRecoveryCodeSet(
        int $accountInternalId,
        bool $forUpdate = false,
    ): ?RecoveryCodeSet;

    public function consumeRecoveryCode(
        RecoveryCodeSet $set,
        RecoveryCodeHash $hash,
        DateTimeImmutable $now,
    ): bool;

    public function revokeActiveRecoveryCodeSet(int $accountInternalId, DateTimeImmutable $now): void;
}
