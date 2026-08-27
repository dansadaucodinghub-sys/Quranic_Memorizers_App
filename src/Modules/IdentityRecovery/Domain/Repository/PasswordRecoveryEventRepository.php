<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryEventType;

interface PasswordRecoveryEventRepository
{
    public function append(
        int $challengeInternalId,
        int $accountInternalId,
        PasswordRecoveryEventType $type,
        DateTimeImmutable $occurredAt,
        ?int $attemptNumber = null,
        ?string $failureCode = null,
        ?string $correlationId = null,
    ): void;

    /** @return list<array<string, int|string|null>> */
    public function listByChallenge(int $challengeInternalId, int $limit = 50): array;

    /** @return list<array<string, int|string|null>> */
    public function listByAccount(int $accountInternalId, int $limit = 50): array;
}
