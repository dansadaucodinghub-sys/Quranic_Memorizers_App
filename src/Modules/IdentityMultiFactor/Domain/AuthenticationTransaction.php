<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AuthenticationTransaction
{
    /** @param list<AuthenticationMethod> $allowedMethods */
    public function __construct(
        public int $internalId,
        public string $publicId,
        public AuthenticationTransactionSecretHash $secretHash,
        public ?int $accountInternalId,
        public ?int $sessionInternalId,
        public AuthenticationTransactionPurpose $purpose,
        public ?StepUpAction $targetAction,
        public ?AuthenticationMethod $primaryMethod,
        public array $allowedMethods,
        public AuthenticationTransactionStatus $status,
        public int $attemptCount,
        public int $maximumAttempts,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $revokedAt,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (
            $allowedMethods === [] || count($allowedMethods) > 4
            || count(array_unique(array_map(
                static fn (AuthenticationMethod $method): string => $method->value,
                $allowedMethods
            ))) !== count($allowedMethods)
            || $attemptCount < 0 || $maximumAttempts < 1 || $attemptCount > $maximumAttempts
            || $version < 1 || $expiresAt <= $createdAt
            || $purpose === AuthenticationTransactionPurpose::LOGIN_MFA && $accountInternalId === null
            || $purpose === AuthenticationTransactionPurpose::STEP_UP
                && ($accountInternalId === null || $sessionInternalId === null || $targetAction === null)
        ) {
            throw new InvalidArgumentException('Authentication transaction is inconsistent.');
        }
    }

    public function usableAt(DateTimeImmutable $now): bool
    {
        return $this->status === AuthenticationTransactionStatus::PENDING
            && $now < $this->expiresAt
            && $this->attemptCount < $this->maximumAttempts;
    }
}
