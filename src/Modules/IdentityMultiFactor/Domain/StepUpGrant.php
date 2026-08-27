<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use DateTimeImmutable;

final readonly class StepUpGrant
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public int $accountInternalId,
        public int $sessionInternalId,
        public StepUpAction $action,
        public AuthenticationAssuranceLevel $assuranceLevel,
        public StepUpGrantStatus $status,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $consumedAt,
        public ?DateTimeImmutable $revokedAt,
        public int $version,
    ) {
        if ($version < 1 || $expiresAt <= $issuedAt) {
            throw new \InvalidArgumentException('Step-up grant is inconsistent.');
        }
    }

    public function permits(
        int $accountInternalId,
        int $sessionInternalId,
        StepUpAction $action,
        DateTimeImmutable $now,
    ): bool {
        return $this->status === StepUpGrantStatus::ACTIVE
            && $this->accountInternalId === $accountInternalId
            && $this->sessionInternalId === $sessionInternalId
            && $this->action === $action
            && $this->assuranceLevel->satisfies($action->requirement())
            && $now < $this->expiresAt;
    }
}
