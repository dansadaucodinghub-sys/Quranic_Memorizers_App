<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransaction;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;

interface AuthenticationTransactionRepository
{
    /** @param list<AuthenticationMethod> $allowedMethods */
    public function createTransaction(
        ?int $accountInternalId,
        ?int $sessionInternalId,
        AuthenticationTransactionPurpose $purpose,
        ?StepUpAction $targetAction,
        ?AuthenticationMethod $primaryMethod,
        array $allowedMethods,
        int $maximumAttempts,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): AuthenticationTransactionCookieValue;

    public function findTransaction(
        AuthenticationTransactionCookieValue $cookie,
        bool $forUpdate = false,
    ): ?AuthenticationTransaction;

    public function recordTransactionFailure(AuthenticationTransaction $transaction, DateTimeImmutable $now): bool;

    public function completeTransaction(AuthenticationTransaction $transaction, DateTimeImmutable $now): bool;

    public function revokePendingStepUpForSession(int $sessionInternalId, DateTimeImmutable $now): void;
}
