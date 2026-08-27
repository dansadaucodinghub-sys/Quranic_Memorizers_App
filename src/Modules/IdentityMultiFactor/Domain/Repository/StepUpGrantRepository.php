<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpGrant;

interface StepUpGrantRepository
{
    public function createGrant(
        int $accountInternalId,
        int $sessionInternalId,
        StepUpAction $action,
        AuthenticationAssuranceLevel $assurance,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): StepUpGrant;

    public function findActiveGrant(
        int $accountInternalId,
        int $sessionInternalId,
        StepUpAction $action,
        bool $forUpdate = false,
    ): ?StepUpGrant;

    public function consumeGrant(StepUpGrant $grant, DateTimeImmutable $now): bool;
}
